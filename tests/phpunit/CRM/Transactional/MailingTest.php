<?php

use CRM_Transactional_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Transactional_MailingTest extends PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
    $this->_mut = new CiviMailUtils($this, TRUE);
  }

  public function tearDown() {
    $this->_mut->stop();
    parent::tearDown();
  }


  /**
   * Helper function to reuse contact creation code.
   *
   * @param $suffix optional suffix to append to names, and email.
   * @throw Exception if fails to create a contact.
   * @return result of api call, with email included.
   */
  public function generateTestContact($suffix = '') {
    $contactParams = [
      'first_name' => 'first' . $suffix . substr(sha1(rand()), 0, 7),
      'last_name' => 'last' . $suffix . substr(sha1(rand()), 0, 7),
      'email' => substr(sha1(rand()), 0, 7) . $suffix . '@example.org',
      'contact_type' => 'Individual',
    ];
    $contact = civicrm_api3('Contact', 'create', $contactParams);

    if ($contact['is_error'] == 1) {
      throw new Exception('Call to api3 Contact create returned an error');
    }
    $contact['email'] = $contactParams['email'];
    return $contact;
  }

  /**
   * Helper function to seperate out getting financial types required
   * for testing receipts.
   * @throw Exeception if fails to get any financial types.
   * @return result of api call.
   */
  private function getFinancialTypes() {
    $results = civicrm_api3('FinancialType', 'get', [
      'sequential' => 1,
    ]);
    if ($results['count'] == 0) {
      throw new Exception('Failed to get any financial types');
    }
    $financial_types = [];
    foreach ($results['values'] as $row) {
      $financial_types[] = $row['name'];
    }
    return $financial_types;
  }

  /**
   * Test transactional mailing is created
   * when a normal email activity is sent.
   */
  public function testSingleEmailActivity() {
    $contact = $this->generateTestContact();
    $contact2 = $this->generateTestContact('2');
    $params = [
      'from' => 'from@example.com',
      'toEmail' => $contact['email'],
      'subject' => 'First Transactional extension unit test.',
      'text' => 'Testing first unit test for transactional extension',
      'html' => "<p>\n Testing first unit test for transactional extension. </p>",
    ];
    $contactDetails = [
      [
        'contact_id' => $contact['id'],
        'sort_name' => $contact['values'][$contact['id']]['sort_name'],
        'display_name' => $contact['values'][$contact['id']]['display_name'],
        'preferred_mail_format' => $contact['values'][$contact['id']]['preferred_mail_format'],
        'email' => $contact['email'],
      ],
    ];
    $subject = "First Transactional extension unit test";
    $text = 'Testing first unit test for transactional extension';
    $html = "<p>\n Testing first unit test for transactional extension. </p>";
    CRM_Activity_BAO_Activity::sendEmail($contactDetails, $subject, $text, $html, $contact['email'], $contact2['id']);

    $mailing = civicrm_api3('Mailing', 'get', [
      'sequential' => 1,
      'name' => ['LIKE' => "%Transactional Email%"],
    ]);
    $this->assertEquals('Transactional Email (Activity Email Sender)', $mailing['values'][0]['name']);
    $this->assertEquals('standalone', $mailing['values'][0]['mailing_type']);
  }

  /**
   * Test transactional mailing is created
   * when schedule reminder is sent.
   */
  public function testScheduleReminderMailing() {
    $contactParams = [
      'contact_type' => 'Individual',
      'email' => 'test-member@example.com',
      'first_name' => 'Churmondleia',
      'last_name' => 'Ōtākou',
    ];

    $actionScheduleParams = [
      'name' => 'sched_eventtype_end_2month_repeat_twice_2_weeks',
      'title' => 'sched_eventtype_end_2month_repeat_twice_2_weeks',
      'body_html' => '<p>body sched_eventtype_end_2month_repeat_twice_2_weeks {event.title}</p>',
      'body_text' => 'body sched_eventtype_end_2month_repeat_twice_2_weeks {event.title}',
      'entity_status' => '1',
      'is_active' => 1,
      'mapping_id' => 2,
      'start_action_condition' => 'before',
      'start_action_date' => 'event_end_date',
      'start_action_offset' => '2',
      'start_action_unit' => 'week',
      'subject' => 'subject sched_eventtype_end_2month_repeat_twice_2_weeks {event.title}',
    ];
    $contact = civicrm_api3('Contact', 'create', $contactParams);

    $eventParams = [
      'contact_id' => $contact['id'],
      'title' => 'Annual CiviCRM meet',
      'event_type_id' => 1,
      'start_date' => date('Ymd', strtotime('-5 day')),
      'end_date' => date('Ymd', strtotime('+2 week')),
    ];
    $event = civicrm_api3('Event', 'create', $eventParams);
    $participant = civicrm_api3('Participant', 'create', ['contact_id' => $contact['id'], 'event_id' => $event['id']]);

    $actionScheduleParams['entity_value'] = 1; //CRM_Core_DAO::getFieldValue('CRM_Event_DAO_Event', $participant['values'][$participant['id']]['event_id'], 'event_type_id');
    $actionSched = civicrm_api3('action_schedule', 'create', $actionScheduleParams);
    civicrm_api3('job', 'send_reminder', []);

    $mailing = civicrm_api3('Mailing', 'get', [
      'sequential' => 1,
      'name' => ['LIKE' => "%Transactional Email%"],
    ]);
    $this->assertEquals('Transactional Email (Scheduled Reminder Sender)', $mailing['values'][0]['name']);
    $this->assertEquals('standalone', $mailing['values'][0]['mailing_type']);

    $transactionalMapping = "SELECT * FROM civicrm_transactional_mapping";
    $dao = CRM_Core_DAO::executeQuery($transactionalMapping);
    if ($dao->fetch()) {
      $this->assertEquals('Scheduled Reminder Sender', $dao->mailing_name);
      $this->assertEquals($actionSched['id'], $dao->entity_id);
    }
  }

  /**
   * Test transactional mailing is created when contribution receipt
   * is sent.
   */
  public function testContributionReceipts() {

    // Set up data required for test - we are going to test each
    // financial type as per
    // https://github.com/fuzionnz/nz.co.fuzion.transactional/issues/20
    $params = [
      'financial_type_id' => NULL,
      'total_amount' => 1,
      'contact_id' => NULL,
      'contribution_status_id' => "Pending",
    ];
    $financial_types = $this->getFinancialTypes();

    // Record the datetime - so we can confirm that the mailing
    // modified time was updated.
    $modified_date = date('Y/m/d H:i');

    // Run test for each financial type.
    foreach ($financial_types as $financial_type) {

      $contact = $this->generateTestContact();
      $params['contact_id'] = $contact['id'];
      $params['financial_type_id'] = $financial_type;

      // Todo chain the creation of the contribution and the sending
      // of the receipt together in one api call.
      $result = civicrm_api3('Contribution', 'create', $params);
      $contribution_id = $result['id'];
      $result = civicrm_api3('Contribution', 'completetransaction', [
        'id' => $contribution_id,
        'is_email_receipt' => 1,
      ]);
      // Test that Reciept Activity Exists
      $activity = civicrm_api3('Activity', 'get', [
        'sequential' => 1,
        'contact_id' => $contact['id'],
        'activity_type_id' => "ReceiptActivity",
      ]);
      $this->assertEquals(1, $activity['count'], "No receipt activity for contact with financial type $financial_type");
    }

    // Test mailing has been created
    $mailing = civicrm_api3('Mailing', 'get', [
      'sequential' => 1,
      'name' => ['=' => "Transactional Email (contribution_online_receipt)"],
    ]);
    $this->assertEquals(1, $mailing['count'], "expected 1 mailing with name: Transactional Email (contribution_online_receipt) found: {$mailing['count']}");

    // Test the mailing was updated recently
    $result = civicrm_api3('Mailing', 'get', [
      'sequential' => 1,
      'name' => ['=' => "Transactional Email (contribution_online_receipt)"],
      'modified_date' => ['>=' => $modified_date],
    ]);
    $this->assertEquals(1, $mailing['count'], "expected 1 mailing updated since $modified_date with name: Transactional Email (contribution_online_receipt) found: {$mailing['count']}");
  }

}
