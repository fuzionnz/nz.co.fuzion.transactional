# Transactional Mail - Bounce and Tracking Handler

This extension adds bounce handling to CiviCRM's transactional emails and creates Activities for mail such as Receipts and Invoices, which by default are not tracked in CiviCRM.

CiviCRM by default only performs bounce handling when sending via CiviMail, but not when sending transactional emails such as scheduled reminders, event registrations, contribution receipts and the like. This extension addresses that limitation.

## Author

This extension was initially written by Dave Reedy on behalf of [Fuzion](https://www.fuzion.co.nz) and substantially extended by Jitendra Purohit.

## Configuration

No special configuration is required for this extension. Just install it!

## How it works

This extension creates a pseudo-CiviMail named "Transactional Emails" which transactional emails are attached to. This could be improved to track by email type with multiple pseudo-CiviMails.

VERP processing is applied to all outgoing transactional (non Civimail) emails, and CiviMail's existing bounce handling may be applied to bounces returned from those emails.

Bounce reporting, delivery, open and click tracking should work as per regular CiviMails.

Standard Activities are created for Receipts and Invoices

### Notes:

* CiviMail doesn't do click tracking for URLs that contain tokens. By time the extension gets the message token replacement has already occurred. It decides to track or not track a URL based on the presence of certain query vars in the URL. There is class variable to specify which query vars to not track, defaulting to cid and cs.
* In the mailing summary report, the time listed for Intended Recipients uses the start time of the job. Since transactional mailings only have one job, all times will be listed the same regardless of when the message was actually sent. If you view the Successful Deliveries you will see the time the message was actually sent.
* It uses a custom template for the mailing summary report. It's replaced using a hook instead of an override.
* This extension should work for 4.6 CiviCRM.

## Sponsorship

Alongside funding from Fuzion and its clients, the development of this extension received financial support from [Agileware](https://agileware.com.au) to give back to the CiviCRM community and support Agileware's CiviCRM customers. Contact Agileware for CiviCRM support and development services in Australia.

## Support

This extension is contributed by [Fuzion](https://www.fuzion.co.nz). 

We welcome contributions and bug reports via the [nz.co.fuzion.transactional issue queue](https://github.com/fuzionnz/nz.co.fuzion.transactional/issues).

Community support is available via CiviCRM community channels:

* [CiviCRM chat](https://chat.civicrm.org)
* [CiviCRM question & answer forum on StackExchange](https://civicrm.stackexchange.com)

Contact us - info@fuzion.co.nz - for professional support and development requests.
