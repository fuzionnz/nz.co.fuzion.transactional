{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2016                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}
<fieldset>
<legend>{ts}Delivery Summary{/ts}</legend>
{if $report.jobs.0.start_date}
  {strip}
  <table class="crm-info-panel">
    <tr><td class="label"><a href="{$report.event_totals.links.queue}">{ts}Intended Recipients{/ts}</a></td>
        <td>{$report.event_totals.queue}</td>
        <td>{$report.event_totals.actionlinks.queue}</td></tr>
    <tr><td class="label"><a href="{$report.event_totals.links.delivered}">{ts}Successful Deliveries{/ts}</a></td>
        <td>{$report.event_totals.delivered} ({$report.event_totals.delivered_rate|string_format:"%0.2f"}%)</td>
        <td>{$report.event_totals.actionlinks.delivered}</td></tr>
  {if $report.mailing.open_tracking}
    <tr><td class="label"><a href="{$report.event_totals.links.opened}&distinct=1">{ts}Unique Opens{/ts}</a></td>
        <td>{$report.event_totals.opened}</td>
        <td>{$report.event_totals.actionlinks.opened}</td></tr>
    <tr><td class="label"><a href="{$report.event_totals.links.opened}">{ts}Total Opens{/ts}</a></td>
        <td>{$report.event_totals.total_opened}</td>
        <td>{$report.event_totals.actionlinks.opened}</td></tr>
  {/if}
  {if $report.mailing.url_tracking}
    <tr><td class="label"><a href="{$report.event_totals.links.clicks}">{ts}Click-throughs{/ts}</a></td>
        <td>{$report.event_totals.url}</td>
        <td>{$report.event_totals.actionlinks.clicks}</td></tr>
  {/if}
  <tr><td class="label"><a href="{$report.event_totals.links.bounce}">{ts}Bounces{/ts}</a></td>
      <td>{$report.event_totals.bounce} ({$report.event_totals.bounce_rate|string_format:"%0.2f"}%)</td>
      <td>{$report.event_totals.actionlinks.bounce}</td></tr>
  </table>
  {/strip}
{else}
    <div class="messages status no-popup">
        {ts}<strong>Delivery has not yet begun for this mailing.</strong> If the scheduled delivery date and time is past, ask the system administrator or technical support contact for your site to verify that the automated mailer task ('cron job') is running - and how frequently.{/ts} {docURL page="user/advanced-configuration/email-system-configuration"}
    </div>
{/if}
</fieldset>

{if $report.mailing.url_tracking && $report.click_through|@count > 0}
<fieldset>
<legend>{ts}Click-through Summary{/ts}</legend>
{strip}
<table class="crm-info-panel">
<tr>
<th><a href="{$report.event_totals.links.clicks}">{ts}Clicks{/ts}</a></th>
<th><a href="{$report.event_totals.links.clicks_unique}">{ts}Unique Clicks{/ts}</a></th>
<th>{ts}Success Rate{/ts}</th>
<th>{ts}URL{/ts}</th>
<th>{ts}Report{/ts}</th></tr>
{foreach from=$report.click_through item=row}
<tr class="{cycle values="odd-row,even-row"}">
<td>{if $row.clicks > 0}<a href="{$row.link}">{$row.clicks}</a>{else}{$row.clicks}{/if}</td>
<td>{if $row.unique > 0}<a href="{$row.link_unique}">{$row.unique}</a>{else}{$row.unique}{/if}</td>
<td>{$row.rate|string_format:"%0.2f"}%</td>
<td><a href="{$row.url}">{$row.url}</a></td>
<td><a href="{$row.report}">Report</a></td>
</tr>
{/foreach}
</table>
{/strip}
</fieldset>
{/if}

<div class="action-link">
    <a href="{$backUrl}" >&raquo; {$backUrlTitle}</a>
</div>





