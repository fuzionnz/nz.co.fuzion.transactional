<?php

class CRM_Mailing_MailingContactGetCountAPIWrapper implements API_Wrapper {

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
    $apiRequest['params']['options']['limit'] = 0;
    return civicrm_api3('MailingContact', 'get', $apiRequest['params'])['count'] ?? 0;
  }

}
