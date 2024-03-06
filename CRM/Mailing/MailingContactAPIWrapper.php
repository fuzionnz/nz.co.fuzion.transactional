<?php

class CRM_Mailing_MailingContactAPIWrapper implements API_Wrapper {

  public function fromApiInput($apiRequest) {
    return $apiRequest;
  }

  /**
  * alter the result before returning it to the caller.
  */
  public function toApiOutput($apiRequest, $result) {
    $transactionalMailings = \Civi::settings()->get('transactional_mailings');

    if (empty($apiRequest['params']['contact_id']) || empty($transactionalMailings)) {
      return $result;
    }

    $transactionalMailings = array_column($transactionalMailings, 'mailing_id');
    foreach ($result['values'] as $key => $mailing) {
      if (in_array($key, $transactionalMailings)) {
        unset($result['values'][$key]);
        $result['count']--;
      }
    }
    return $result;
  }

}
