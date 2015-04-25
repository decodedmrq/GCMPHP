<?php
/**
 * Created by IntelliJ IDEA.
 * User: Mon
 * Date: 4/22/2015
 * Time: 8:50 AM
 */

namespace GCM;


class Sender
{
    /*
     * Delay retry
     */
    const BACKOFF_INITIAL_DELAY = 1000;

    /**
     * Maximum delay before a retry.
     */
    const MAX_BACKOFF_DELAY = 1024000;
    const PAYLOAD_PREFIX = 'data.';
    const DEFAULT_RETRY = 5;

    const GCM_ENDPOINT = 'https://android.googleapis.com/gcm/send';
    private $apiKey;
    private $logs;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function send(Message $message, $registrationId, $retries = Sender::DEFAULT_RETRY)
    {
        $attempt = 0;
        $result = null;
        $backoff = Sender::BACKOFF_INITIAL_DELAY;
        do {
            $attempt++;
            $result = $this->sendNoRetry($message, $registrationId);
            $tryAgain = $result == null && $attempt <= $retries;
            if ($tryAgain) {
                $sleepTime = $backoff / 2 + rand(0, $backoff);
                sleep($sleepTime / 1000);
                if (2 * $backoff < Sender::MAX_BACKOFF_DELAY)
                    $backoff *= 2;
            }
        } while ($tryAgain);
        if (is_null($result))
            throw new \Exception('Could not send message after ' . $attempt . ' attempts');

        return $result;
    }

    public function sendNoRetry(Message $message, $registrationId)
    {
        if (!$this->getApiKey()) {
            throw new \InvalidArgumentException("Invalid API key");
        }
        $post = [
            'registration_id' => $registrationId,
            'collapse_key' => $message->getCollapseKey(),
            'delay_while_idle' => $message->getDelayWhileIdle(),
            'time_to_live' => $message->getTimeToLive(),
            'restricted_package_name' => $message->getRestrictedPackageName(),
            'dry_run' => $message->getDryRun(),
            'data.message' => $message->getData(),
        ];

        $result = $this->post(Sender::GCM_ENDPOINT, $post);
        if ($result['response_code'] == 200) {
            $response = $result['response'];
        } elseif ($result['response_code'] == 503) {
            return null;
        } elseif (strpos($result['response_code'], '5') == 0) {
            throw new \Exception("[{$result['response_code']}] GCM Service is unavailable");
        } elseif ($result['response_code'] == 400) {
            throw new \Exception("Error parse JSON requests {$result['response']}");
        } else {
            throw new \Exception("Error response {$result['response']}");
        }

        return new Result($response, $registrationId);
    }

    public function sendMulti(Message $message, array $registrationIds, $retries = Sender::DEFAULT_RETRY)
    {
        $attempt = 0;
        $multicastResult = null;
        $backoff = Sender::BACKOFF_INITIAL_DELAY;
        // results by registration id, it will be updated after each attempt
        // to send the messages
        $results = [];
        $unsentRegIds = $registrationIds;
        $multicastIds = [];
        do {
            $attempt++;
            $multicastResult = $this->sendNoRetryMulti($message, $unsentRegIds);
            $multicastId = $multicastResult->getMulticastId();
            $multicastIds[] = $multicastId;
            $unsentRegIds = $this->updateStatus($unsentRegIds, $results, $multicastResult);
            $tryAgain = count($unsentRegIds) > 0 && $attempt <= $retries;
            if ($tryAgain) {
                $sleepTime = $backoff / 2 + rand(0, $backoff);
                sleep($sleepTime / 1000);
                if (2 * $backoff < Sender::MAX_BACKOFF_DELAY)
                    $backoff *= 2;
            }
        } while ($tryAgain);
        $success = $failure = $canonicalIds = 0;
        foreach ($results as $result) {
            if (!is_null($result->getMessageId())) {
                $success++;
                if (!is_null($result->getCanonicalRegistrationId()))
                    $canonicalIds++;
            } else {
                $failure++;
            }
        }
        $multicastId = $multicastIds[0];
        $builder = new MulticastResult();
        $builder->setSuccess($success);
        $builder->setFailure($failure);
        $builder->setCanonicalIds($canonicalIds);
        $builder->setMulticastId($multicastId);
        $builder->setRetryMulticastIds($multicastIds);
        // add results, in the same order as the input
        foreach ($registrationIds as $registrationId) {
            $builder->addResult($results[$registrationId]);
        }

        return $builder;
    }

    public function sendNoRetryMulti(Message $message, array $registrationIds)
    {
        if (is_null($registrationIds) || count($registrationIds) == 0)
            throw new \InvalidArgumentException('registrationIds cannot be null or empty');
        $post = [
            'registration_ids' => $registrationIds,
            'collapse_key' => $message->getCollapseKey(),
            'delay_while_idle' => $message->getDelayWhileIdle(),
            'time_to_live' => $message->getTimeToLive(),
            'restricted_package_name' => $message->getRestrictedPackageName(),
            'dry_run' => $message->getDryRun(),
            'data' => [
                'message' => $message->getData(),
            ],
        ];
        $result = $this->post(Sender::GCM_ENDPOINT, $post, 'application/json');
        if ($result['response_code'] == 200) {
            $response = $result['response'];
        } elseif ($result['response_code'] == 503) {
            return null;
        } elseif (strpos($result['response_code'], '5') == 0) {
            throw new \Exception("[{$result['response_code']} GCM Service is unavailable");
        } elseif ($result['response_code'] == 400) {
            throw new \Exception("Error parse JSON requests {$result['response']}");
        } else {
            throw new \Exception("Error response {$result['response']}");
        }

        return new MulticastResult($response, $registrationIds);
    }

    /**
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @param mixed $apiKey
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    protected function post($url, $data, $contentType = 'application/x-www-form-urlencoded;charset=UTF-8')
    {
        if (is_null($this->getApiKey())) {
			throw new \Exception('Invalid API Key.');
        }
        $headers = [
            'Authorization: key=' . $this->getApiKey(),
            'Content-Type: ' . $contentType,
        ];
        //------------------------------
        // Initialize curl handle
        //------------------------------
        if ($contentType == 'application/json') {
            $data = json_encode($data);
        } else {
            $data = http_build_query($data);
        }
        // Open connection
         $ch = curl_init();
   
         // Set the url, number of POST vars, POST data
         curl_setopt($ch, CURLOPT_URL, $url);
   
         curl_setopt($ch, CURLOPT_POST, true);
         curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   
         // Disabling SSL Certificate support temporarly
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
   
         curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        //------------------------------
        // Actually send the push!
        //------------------------------
        $result['response'] = curl_exec($ch);

        //------------------------------
        // Error? Display it!
        //------------------------------
        $result['response_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            throw new \Exception('GCM error: ' . curl_error($ch));
        }
        // Close connection
        curl_close($ch);

        return $result;
    }

    /**
     * @return mixed
     */
    public function getLogs()
    {
        return $this->logs;
    }

    /**
     * @param mixed $logs
     */
    public function log($logs)
    {
        if (is_array($logs)) {
            $this->logs = $logs;
        } else {
            $this->logs[] = $logs;
        }
    }

    private function updateStatus($unsentRegIds, &$allResults, MulticastResult $multicastResult)
    {
        $results = $multicastResult->getResults();
        if (count($results) != count($unsentRegIds)) {
            // should never happen, unless there is a flaw in the algorithm
            throw new \RuntimeException('Internal error: sizes do not match. currentResults: ' . $results . '; unsentRegIds: ' + $unsentRegIds);
        }
        $newUnsentRegIds = [];
        for ($i = 0; $i < count($unsentRegIds); $i++) {
            $regId = $unsentRegIds[$i];
            $result = $results[$i];
            $allResults[$regId] = $result;
            $error = $result->getError();
            $result->setRegistrationId($regId);
            if (!is_null($error) && $error == 'Unavailable')
                $newUnsentRegIds[] = $regId;
        }

        return $newUnsentRegIds;
    }
}