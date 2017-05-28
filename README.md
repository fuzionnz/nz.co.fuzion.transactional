# Transactional Bounce Handler

This extension adds bounce handling to CiviCRM's transactional emails.

CiviCRM by default only performs bounce handling when sending via CiviMail, but not when sending transactional emails such as scheduled reminders, event registrations, contribution receipts and the like.

## Author

This extension was written by Dave Reedy on behalf of Fuzion Aotearoa.

## Configuration

No special configuration is required for this extension. Just install it!

## How it works

This extension creates a pseudo-CiviMail named "Transactional Emails" which transactional emails are attached to. This could be improved to track by email type with multiple pseudo-CiviMails.

VERP processing can be applied to outgoing transactional emails, and CiviMail's existing bounce handling may  be applied to bounces returned from those emails.

Bounce reporting, delivery, open and click tracking should work as per regular CiviMails.

### Notes:

* CiviMail doesn't do click tracking for URLs that contain tokens. By time the extension gets the message token replacement has already occurred. It decides to track or not track a URL based on the presence of certain query vars in the URL. There is class variable to specify which query vars to not track, defaulting to cid and cs.
* In the mailing summary report, the time listed for Intended Recipients uses the start time of the job. Since transactional mailings only have one job, all times will be listed the same regardless of when the message was actually sent. If you view the Successful Deliveries you will see the time the message was actually sent.
* It uses a custom template for the mailing summary report. It's replaced using a hook instead of an override.
* This extension should work for 4.6 CiviCRM.

## Support

This extension is contributed by [Fuzion Aotearoa](https://www.fuzion.co.nz). Contact us for professional support and development requests.

We welcome contributions and bug reports via the extension's Github issue queue.

Community support is available via CiviCRM community channels:

* [CiviCRM chat](https://chat.civicrm.org)
* [CiviCRM question & answer forum on StackExchange](https://civicrm.stackexchange.com)
