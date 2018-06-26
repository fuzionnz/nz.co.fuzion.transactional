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
class CRM_Transactional_MailingTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

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
   * Test transactional mailing is created
   * when a normal email activity is sent.
   */
  public function testSingleEmailActivity() {
    $contactParams = array(
      'first_name' => 'first' . substr(sha1(rand()), 0, 7),
      'last_name' => 'last' . substr(sha1(rand()), 0, 7),
      'email' => substr(sha1(rand()), 0, 7) . '@example.org',
      'contact_type' => 'Individual',
    );
    $contact = civicrm_api3('Contact', 'create', $contactParams);

    $contactParams2 = array(
      'first_name' => 'first2' . substr(sha1(rand()), 0, 7),
      'last_name' => 'last2' . substr(sha1(rand()), 0, 7),
      'email' => substr(sha1(rand()), 0, 7) . '2@example.org',
      'contact_type' => 'Individual',
    );
    $contact2 = civicrm_api3('Contact', 'create', $contactParams2);

    $params = array(
      'from' => 'from@example.com',
      'toEmail' => $contactParams['email'],
      'subject' => 'First Transactional extension unit test.',
      'text' => 'Testing first unit test for transactional extension',
      'html' => "<p>\n Testing first unit test for transactional extension. </p>",
    );
    $contactDetails = array(
      array(
        'contact_id' => $contact['id'],
        'sort_name' => $contact['values'][$contact['id']]['sort_name'],
        'display_name' => $contact['values'][$contact['id']]['display_name'],
        'preferred_mail_format' => $contact['values'][$contact['id']]['preferred_mail_format'],
        'email' => $contactParams['email'],
      )
    );
    $subject = "First Transactional extension unit test";
    $text = 'Testing first unit test for transactional extension';
    $html = "<p>\n Testing first unit test for transactional extension. </p>";
    CRM_Activity_BAO_Activity::sendEmail($contactDetails, $subject, $text, $html, $contactParams['email'], $contact2['id']);

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
    $contactParams = array(
      'contact_type' => 'Individual',
      'email' => 'test-member@example.com',
      'first_name' => 'Churmondleia',
      'last_name' => 'Ōtākou',
    );

    $actionScheduleParams = array(
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
    );
    $contact = civicrm_api3('Contact', 'create', $contactParams);

    $eventParams = array(
      'contact_id' => $contact['id'],
      'title' => 'Annual CiviCRM meet',
      'event_type_id' => 1,
      'start_date' => date('Ymd', strtotime('-5 day')),
      'end_date' => date('Ymd', strtotime('+2 week')),
    );
    $event = civicrm_api3('Event', 'create', $eventParams);
    $participant = civicrm_api3('Participant', 'create', array('contact_id' => $contact['id'], 'event_id' => $event['id']));

    $actionScheduleParams['entity_value'] = 1; //CRM_Core_DAO::getFieldValue('CRM_Event_DAO_Event', $participant['values'][$participant['id']]['event_id'], 'event_type_id');
    $actionSched = civicrm_api3('action_schedule', 'create', $actionScheduleParams);
    civicrm_api3('job', 'send_reminder', array());

    $mailing = civicrm_api3('Mailing', 'get', [
      'sequential' => 1,
      'name' => ['LIKE' => "%Transactional Email%"],
    ]);
    $this->assertEquals('Transactional Email (Scheduled Reminder Sender)', $mailing['values'][0]['name']);
    $this->assertEquals('standalone', $mailing['values'][0]['mailing_type']);

    $transactionalMapping = "SELECT * FROM civicrm_transactional_mapping";
    $dao = CRM_Core_DAO::executeQuery($transactionalMapping);
    if ($dao->fetch()) {
      $this->assertEquals('Scheduled Reminder Sender', $dao->option_group_name);
      $this->assertEquals($actionSched['id'], $dao->entity_id);
    }
  }

}
