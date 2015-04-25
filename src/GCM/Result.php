<?php
/**
 * Created by IntelliJ IDEA.
 * User: Mon
 * Date: 4/22/2015
 * Time: 8:50 AM
 */

namespace GCM;


class Result
{
    private $messageId;
    private $canonicalRegistrationId;
    private $error;
    private $invalidRegistrationId;
    private $registrationId;

    public function __construct($response = null, $registrationId = null)
    {
        if (is_null($response) && is_null($registrationId)) {
            return $this;
        }
        $lines = explode("\n", $response);
        $responseParts = explode('=', urldecode($lines[0]));
        $token = $responseParts[0];
        $value = $responseParts[1];
        if ($token == 'Error') {
            $this->setError($value);
            if ($value == 'InvalidRegistration' || $value == 'NotRegistered') {
                $this->setInvalidRegistrationId($registrationId);
            }
        } elseif ($token == 'id') {
            $this->setMessageId($value);
            if (isset($lines[1]) && $lines[1] != '') {
                $responseParts = explode('=', $lines[1]);
                $token = $responseParts[0];
                $value = $responseParts[1];
                if ($token == 'registration_id')
                    $this->setCanonicalRegistrationId($value);
            }
        } else {
            throw new \Exception('Received invalid response from GCM: ' . $lines[0]);
        }
    }

    /**
     * @return mixed
     */
    public function getMessageId()
    {
        return $this->messageId;
    }

    /**
     * @param mixed $messageId
     */
    public function setMessageId($messageId)
    {
        $this->messageId = $messageId;
    }

    /**
     * @return mixed
     */
    public function getCanonicalRegistrationId()
    {
        return $this->canonicalRegistrationId;
    }

    /**
     * @param mixed $canonicalRegistrationId
     */
    public function setCanonicalRegistrationId($canonicalRegistrationId)
    {
        $this->canonicalRegistrationId = $canonicalRegistrationId;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param mixed $error
     */
    public function setError($error)
    {
        $this->error = $error;
    }

    /**
     * @return mixed
     */
    public function getInvalidRegistrationId()
    {
        return $this->invalidRegistrationId;
    }

    /**
     * @param mixed $invalidRegistrationId
     */
    public function setInvalidRegistrationId($invalidRegistrationId)
    {
        $this->invalidRegistrationId = $invalidRegistrationId;
    }

    /**
     * @return mixed
     */
    public function getRegistrationId()
    {
        return $this->registrationId;
    }

    /**
     * @param mixed $registrationId
     */
    public function setRegistrationId($registrationId)
    {
        $this->registrationId = $registrationId;
    }

}