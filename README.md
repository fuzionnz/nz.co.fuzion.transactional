# Transactional Mail

This extension adds **bounce handling** and **click tracking**, and **creates activities** for CiviCRM's transactional emails (i.e event registrations, contribution receipts, invoices, scheduled reminders, and so on).

Out of the box, CiviCRM only does the things mentioned above when sending via CiviMail. This extension adds that functionality to all mail sent from CiviCRM.

## Author

This extension was initially written by Dave Reedy on behalf of [Fuzion](https://www.fuzion.co.nz) and substantially extended by Jitendra Purohit.

## Configuration

No special configuration is required for this extension. Just install it!

WARNING

Because of the way CiviCRM processes CC's and BCC's these get VERPIFIED also. Unfortunately these link back to the TO recipient not the BCC or CC. If any email that is used as a CC or a BCC starts bouncing you will end up with a lot of contacts put on hold for bounces not related to their address.

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
* Note it is not recommended to enable this extension without the CiviMail Component enabled.
* Make sure you have `open` and `click-through` tracking enabled by default at CiviMail Component Settings => `/civicrm/admin/setting/preferences/mailing?reset=1`, else they'll not be tracked for transactional emails as well.

## Sponsorship

Alongside funding from Fuzion and its clients, the development of this extension received financial support from [Agileware](https://agileware.com.au) to give back to the CiviCRM community and support Agileware's CiviCRM customers. Contact Agileware for CiviCRM support and development services in Australia.

## Support

This extension is contributed by [Fuzion](https://www.fuzion.co.nz).

We welcome contributions and bug reports via the [nz.co.fuzion.transactional issue queue](https://github.com/fuzionnz/nz.co.fuzion.transactional/issues).

Community support is available via CiviCRM community channels:

* [CiviCRM chat](https://chat.civicrm.org)
* [CiviCRM question & answer forum on StackExchange](https://civicrm.stackexchange.com)

Contact us - info@fuzion.co.nz - for professional support and development requests.
