<?php
/**
 * Created by IntelliJ IDEA.
 * User: Mon
 * Date: 4/22/2015
 * Time: 8:49 AM
 */

namespace GCM;


class MulticastResult
{

    private $success;
    private $failure;
    private $canonicalIds;
    private $multicastId;
    private $results;
    private $retryMulticastIds;
    private $invalidRegistrationIds;

    public function __construct($response = null, $registrationIds = null)
    {
        if (is_null($response) && is_null($registrationIds)) {
            return $this;
        }
        $response = json_decode($response);
        $this->setFailure($response->failure);
        $this->setSuccess($response->success);
        $this->setCanonicalIds($response->canonical_ids);
        $this->setMulticastId($response->multicast_id);
        if ($response->results) {
            $results = $response->results;
            foreach ($results as $index => $result) {
                $messageId = isset($result->message_id) ? $result->message_id : null;
                $canonicalRegId = isset($result->registration_id) ? $result->registration_id : null;
                $error = isset($result->error) ? $result->error : null;
                $result = new Result();
                $result->setMessageId($messageId);
                $result->setCanonicalRegistrationId($canonicalRegId);
                $result->setError($error);
                $this->addResult($result);
            }
        }
    }

    /**
     * Add a result to the result property
     *
     * @param Result $result
     */
    public function addResult(Result $result)
    {
        $this->results[] = $result;
    }

    /**
     * Gets the multicast id.
     *
     * @return string
     */
    public function getMulticastId()
    {
        return $this->multicastId;
    }

    /**
     * Gets the number of successful messages.
     *
     * @return int
     */
    public function getSuccess()
    {
        return $this->success;
    }

    /**
     * Gets the total number of messages sent, regardless of the status.
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->success + $this->failure;
    }

    /**
     * Gets the number of failed messages.
     *
     * @return int
     */
    public function getFailure()
    {
        return $this->failure;
    }

    /**
     * Gets the number of successful messages that also returned a canonical
     * registration id.
     *
     * @return int
     */
    public function getCanonicalIds()
    {
        return $this->canonicalIds;
    }

    /**
     * Gets the results of each individual message
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Gets additional ids if more than one multicast message was sent.
     *
     * @return array
     */
    public function getRetryMulticastIds()
    {
        return $this->retryMulticastIds;
    }

    /**
     * @param mixed $success
     */
    public function setSuccess($success)
    {
        $this->success = $success;
    }

    /**
     * @param mixed $failure
     */
    public function setFailure($failure)
    {
        $this->failure = $failure;
    }

    /**
     * @param mixed $canonicalIds
     */
    public function setCanonicalIds($canonicalIds)
    {
        $this->canonicalIds = $canonicalIds;
    }

    /**
     * @param mixed $multicastId
     */
    public function setMulticastId($multicastId)
    {
        $this->multicastId = $multicastId;
    }

    /**
     * @param mixed $retryMulticastIds
     */
    public function setRetryMulticastIds($retryMulticastIds)
    {
        $this->retryMulticastIds = $retryMulticastIds;
    }

    /**
     * @return mixed
     */
    public function getInvalidRegistrationIds()
    {
        foreach ($this->results as $result) {
            if ($result->getError() == 'NotRegistered' || $result->getError() == 'InvalidRegistration') {
                $this->invalidRegistrationIds[] = $result->getRegistrationId();
            } else {
                $this->invalidRegistrationIds = [];
            }
        }

        return $this->invalidRegistrationIds;
    }

}