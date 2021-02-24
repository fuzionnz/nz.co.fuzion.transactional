<?php

class CRM_Mailing_MailingContactGetCountAPIWrapper implements API_Wrapper {

  public function fromApiInput($apiRequest) {
    return $apiRequest;
  }

  /**
  * alter the result before returning it to the caller.
  */
  public function toApiOutput($apiRequest, $result) {
    $transactionalMailings = civicrm_api3('Setting', 'get', [
      'sequential' => 1,
      'return' => ["transactional_mailings"],
    ]);
    if (empty($transactionalMailings['values'][0]['transactional_mailings'])) {
      return $result;
    }
    return $result - count($transactionalMailings['values'][0]['transactional_mailings']);
  }

}