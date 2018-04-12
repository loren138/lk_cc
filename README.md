LK Constant Contact
=====

## This is not compatible with API v2 from Constant Contact.
I will not be updating the plugin to version 2 as we are no longer using Expression Engine on our site.  If you would like to update it, I'm willing to merge a pull request or hand off development entirely.  Alternatively, if you would like to sponsor an update to version 2, please contact me.

Constant Contact Plugin for ExpressionEngine

This addon interfaces with the constant contact API to allow website visitors to sign up for your newsletters by entering their email address and selecting the lists that they wish to sign up for.  It does not interface with current users in your EE memberbase.

If your multiple sites all use the same Constant Contact account this will work with multi-site manager.

If you would like to add features to this addon, feel free to fork the git-repository and/or work with me on it.  I unfortunately have a full time job and won't be able to add features unless they are required for one of my projects, but I will do my best to provide support to those using the addon and help anyone who wants to add more features to it.

If you are looking for a robust way to send out campaigns, check out https://www.devdemon.com/campaigns/.  Note: It currently does not have a way to add subscribers, just to email the existing ones.

Extension Settings
=====================================================
* Constant Contact Username: Your username
* Constant Contact Password: Your password
* Constant Contact API Key:
	* Get your API Key Here: http://community.constantcontact.com/t5/Documentation/API-Keys/ba-p/25015
	* Login, Click Request a New API Key
	* Fill in the form:
		* Multiple Accounts?  Select Yes if you will use this key on multiple websites otherwise select no.
		* Application Description - Website email signup form
		* You may leave the rest of the form blank.
	* Once you recieve a key, copy the key into the API Key setting.
* Save your settings.
* Click settings again.  If everything is working, your lists should now be listed in the List IDs multiselect.
* The number before the list name is the ID which will be used in the tags.

Plugin Basic Usage
=====================================================
Subscribe Tag Usage
----------------------
* Default_list is the lists that should be checked by default, if omitted no lists will be checked by default
* Show_list is the lists to be shown, if omitted all lists will be shown.  The lists will be shown in the order the ids are listed.
* (You can find your list IDs in the extension settings.)
* Default_list and Show_list both support list ids separated by pipes.  They do not support the use of "not".
* Email and at least one list selection will be required, the name fields are optional.  Error message strings are defined in the language file.
* First and last name fields can be omitted if desired.  This script does not currently support any other Constant Contact fields.
* XID Hash is for EE 2.7+ so it will allow the submission

```html
	{exp:lk_cc:subscribe default_list="3" show_list="2|5|6|3|4" parse="inward"}
		{if "{success2}" == "false"}
			<form action="{path="site/subscribe"}" method="post">
				<input type="hidden" name="XID" value="{XID_HASH}" />
				<div class="clearboth">Please confirm your subscription.</div>
				<div class="clearboth error">{error_message}</div>
				<div class="float-left">
					<p><sup>*</sup>Your Email:<br /><input type="text" name="email" value="{email}" /></p>
					<p>First Name:<br /> <input type="text" name="first_name" value="{first_name}" /></p>
					<p>Last Name:<br /> <input type="text" name="last_name" value="{last_name}" /></p>
					<p>Custom Field 1:<br /> <input type="text" name="custom_field_1" value="{custom_field_1}" size="30" /></p>
					<p>State Code:<br /> {exp:reegion_select:states selected="{state_code}" name="state_code" type="alpha2"}</p>
				</div>
				<div class="float-left"><ul class="none">
					<li>Subscribe to:</li>
					{lists}
						<li id="chk_li_{value}">
							<input id="chk_{value}" type="checkbox" value="{value}" name="lists[]" {checked} /> 
							<label for="chk_{value}">{name}</label>
						</li>
					{/lists}
				</ul></div>
				<div class="clearboth">
					<p><input type="submit" name="submit" value="Submit" class="contact" /></p></div>
			</form>
		{if:else}
			<p>Thanks for subscribing!  Please check your email to confirm your subscription.</p>
		{/if}
	{/exp:lk_cc:subscribe}
```
		
Small form with just email:
--------------------------------------------
This form should submit to the page with the plugin tag code and can be used to embed an email subscribe form into all your website pages without calling the plugin until it is needed.
* Set the "confirm" hidden field so that "success" will not be set allowing the user to confirm, subscribe to additional lists etc.
* If you set default lists, when the user clicks submit they will be added to your default email lists so even if they don't submit the confirmation form, they have still be subscribed.
* Submitting the confirmation will allow them edit their information.
* XID hash is for EE 2.7+ so that it will allow the submission

```html
	<form action="/asia/site/subscribe" target="_blank" method="post">
		<fieldset>
			 <input type="hidden" name="XID" value="{XID_HASH}" />
			<input type="hidden" name="confirm" value="confirm" />
			<label for="subscribe_email" class="screen-reader-text">Email Address</label> 
			<input type="text" value="" id="subscribe_email" name="email" />
			<input type="image" alt="Subscribe" src="/design/submit-button.png" value="Go" name="go" id="subscribe_image" />
		</fieldset>
	</form>
```

Creating a Campaign:
--------------------------------------------
This will create a new campaign in constant contact.

* htmlspecialchars() will be run on the email content and email text content
* Lists is pipe separated list ids (List Ids are in the extension settings)
* It will not work if EE's fancy quotes are in the title
* Please make sure the template that creates campaigns is only accessible by admins to prevent users or search bots from accidentally creating campaigns.
* The campaign syntax is very strict, please use my code as a model.  It basically mirrors the CC XML api just using brackets for the tags.
* Your email XML must be perfect or CC will reject it.  I recommend building the XML without using the exp:lk_cc:campaign tags and just copy pasting into the CC Custom Campaign builder until your code works and the preview looks right.  Then, put the exp:lk_cc:campaign tags around it which will create the campaign.  This will prevent you from getting a bunch of test campaigns created.  See the advanced users guide for more details: http://www.constantcontact.com/aka/docs/pdf/CC_Advanced_Editor_User_Guide.pdf
* This does not schedule the campaign to be sent.  You must log into constant contact and do that yourself.  (You should also preview the campaign before you schedule it just to be sure everything looks right.)
* In order for this tag to work properly and fetch after all other processing has happened, this tag schedules a late fetch of the data which means you should have nothing in this template except the exp:lk_cc:campaign information.  The plugin will output the full XML that it sends to Constant Contact and the result of scheduling the campaign at the bottom of the page for use in debugging.
* Using a CSS template type will make the output more readable for debugging, but switching to html and putting <!-- --> around the [campaign] [/campaign] will hide the information for users

IMPORTANT: Just to say it one more time, the pages that this is on are intended for use only by the admin.  Everytime this page is hit, the plugin will attempt to create a new constant contact campaign.  This plugin has no ability to detect new posts and only create a campaign for each new post.  Finally, the constant contact API is very picky about the XML it receives, and the plugin does very little to verify your XML so be sure you get it right using my example code below should help with that.


Example Code:
```
{exp:lk_cc:campaign}
[campaign]
{exp:channel:entries channel="features" limit="1" disable="categories|member_data|pagination|category_fields"}{if no_results}404 Error{/if}
<?php
$rss = <<<EOT
{feature_rss}
EOT;
$rss = trim(iconv("UTF-8", 'ASCII//TRANSLIT', $rss)); {!-- Convert UTF-8 characters to ASCII, optional, but good to do if you have an English website as it gets rid of Word fancy quotes and dashes --}
?>
<?php {!-- no special/funky characters are allowed in titles for constant contact so get rid of those fancy quotes from EE --}
$title = <<<EOT
{title}
EOT;
$title = str_replace(array('&#8220;','&#8221;'),'"',$title);
$title = trim(iconv("UTF-8", 'ASCII//TRANSLIT', $title));
?>
      [debug]off[/debug] {!-- If set to on, the debug information will be displayed as exactly what was sent to constant contact as XML and your type --}
      [name]<?php echo $title; ?> {current_time format="%Y-%m-%d %H:%i:%s"}[/name]{!-- no duplicate names are allowed so post fix the current time --}
      [Subject]New Post: <?php echo $title; ?>[/Subject]
      [FromName]Your Website[/FromName]
      [ViewAsWebpage]NO[/ViewAsWebpage]
      [ViewAsWebpageLinkText]Click here[/ViewAsWebpageLinkText]
      [ViewAsWebpageText]Having trouble viewing this email?[/ViewAsWebpageText]
      [PermissionReminder]NO[/PermissionReminder]
      [PermissionReminderText]NO[/PermissionReminderText]
      [GreetingName]FirstName[/GreetingName]
      [GreetingSalutation]Dear[/GreetingSalutation]
      [GreetingString]Greetings![/GreetingString]
      [OrganizationName]Company[/OrganizationName]
      [OrganizationAddress1]Address[/OrganizationAddress1]
      [OrganizationAddress2][/OrganizationAddress2]
      [OrganizationAddress3][/OrganizationAddress3]
      [OrganizationCity]City[/OrganizationCity]
      [OrganizationState]Virginia[/OrganizationState]
      [OrganizationInternationalState][/OrganizationInternationalState]
      [OrganizationCountry]United States[/OrganizationCountry]
      [OrganizationPostalCode]99999[/OrganizationPostalCode]
      [IncludeForwardEmail]YES[/IncludeForwardEmail]
      [ForwardEmailLinkText]Forward email[/ForwardEmailLinkText]
      [IncludeSubscribeLink]YES[/IncludeSubscribeLink]
      [SubscribeLinkText]Subscribe me![/SubscribeLinkText]
      [EmailContentFormat]XHTML[/EmailContentFormat]
      [FromEmail]
        [EmailAddress]email@domain.com[/EmailAddress] {!-- MUST be a VERIFIED email address on your Constant Contact account --}
      [/FromEmail]
      [ReplyToEmail]
        [EmailAddress]email@domain.com[/EmailAddress] {!-- MUST be a VERIFIED email address on your Constant Contact account --}
      [/ReplyToEmail]
      [Lists]3[/Lists]
[EmailContent]
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
	<body class="body"><div align="center" class="body">{!--duplicate body class to ensure background color --}<OpenTracking/>
		<table class="header">
			<tr><td style="padding:0;text-align:left;width:650px;"><table style="border:none;width:100%"><tr><td style="padding:0 0 5px 0;text-align:left;">
		<SimpleURLProperty name="{site_name}.logo" track="true" type="plain" img="{global_design_url}aslogo2.png" {!--
		--}href="{path='site_index'}?utm_source=newsletter&utm_medium=email&utm_campaign={url_title}">
			<a href="{path='site_index'}?utm_source=newsletter&utm_medium=email&utm_campaign={url_title}">
				<img src="{global_design_url}aslogo2.png" alt="AsiaStories" />
			</a></SimpleURLProperty></td>
			<td class="title" style="color:#857362;vertical-align:middle;text-align:right;font-size:16px;letter-spacing:1px;">
			Giving voice to<br />God's work in Asia</td></tr></table></td></tr>
		<tr><td class="padded">
{exp:playa:children limit="1" disable="categories|category_fields|custom_fields|member_data|pagination" parse="inward"}{if no_children}{redirect="404"}{/if}
	{exp:channel_images:images entry_id="{parent:entry_id}" field="feature_169" limit="1"}
		<div class="img"><SimpleURLProperty name="feature.image" track="true" type="plain" img="{image:url:large169}" href="{!-- Pretty way to remove line breaks...
		--}{if channel_short_name=="stories"}{url_title_path="stories/view"}{/if}{!--
		--}{if channel_short_name=="photos"}{url_title_path="photos/view"}{/if}{!--
		--}{if channel_short_name=="videos"}{url_title_path="videos/view"}{/if}{!--
		--}{if channel_short_name=="interactives"}{url_title_path="interactives/view"}{/if}?utm_source=newsletter&utm_medium=email&utm_campaign={parent:url_title}">
		<a href="{!-- Pretty way to remove line breaks...
		--}{if channel_short_name=="stories"}{url_title_path="stories/view"}{/if}{!--
		--}{if channel_short_name=="photos"}{url_title_path="photos/view"}{/if}{!--
		--}{if channel_short_name=="videos"}{url_title_path="videos/view"}{/if}{!--
		--}{if channel_short_name=="interactives"}{url_title_path="interactives/view"}{/if}?utm_source=newsletter&utm_medium=email&utm_campaign={parent:url_title}">
			<img src="{image:url:large169}" alt="Feature Image" style="box-shadow: 1px 1px 4px 2px rgba(0, 0, 0, 0.6);" />
		</a></SimpleURLProperty></div>
	{/exp:channel_images:images}{/exp:playa:children}</td></tr>
	<tr><td><table style="border:none;width:100%"><tr><td class="date">{entry_date format="%F %j, %Y"}</td><td style="text-align:right;">
	<SimpleURLProperty name="facebook" track="true" type="plain" img="{global_design_url}social-facebook.png" {!--
		--}href="http://www.facebook.com/{var_fb_name}?ref=cstories" />
	<SimpleURLProperty name="twitter" track="true" type="plain" img="{global_design_url}social-twitter.png" {!--
		--}href="http://twitter.com/{var_twitter_name}" />
	<SimpleURLProperty name="vimeo" track="true" type="plain" img="{global_design_url}social-vimeo.png" {!--
		--}href="http://vimeo.com/{var_vimeo_name}" />
	<SimpleURLProperty name="pinterest" track="true" type="plain" img="{global_design_url}social-pinterest.png" {!--
		--}href="http://www.pinterest.com/{var_pinterest_name}" />
	<SimpleURLProperty name="rss" track="true" type="plain" img="{global_design_url}social-rss.png" {!--
		--}href="{path="site/feed"}" />
	</td></tr></table></td></tr>
	<tr><td style="color:#D16F1A;vertical-align:bottom;text-align: left;font-size:40px;">
	{exp:playa:children limit="1" disable="categories|category_fields|custom_fields|member_data|pagination" parse="inward"}{if no_children}{redirect="404"}{/if}
	<SimpleURLProperty name="feature.title.orange" track="true" type="plain" label="{exp:mah_eencode}{parent:title}{/exp:mah_eencode}" href="{!-- Pretty way to remove line breaks...
		--}{if channel_short_name=="stories"}{url_title_path="stories/view"}{/if}{!--
		--}{if channel_short_name=="photos"}{url_title_path="photos/view"}{/if}{!--
		--}{if channel_short_name=="videos"}{url_title_path="videos/view"}{/if}{!--
		--}{if channel_short_name=="interactives"}{url_title_path="interactives/view"}{/if}?utm_source=newsletter&utm_medium=email&utm_campaign={parent:url_title}">
		<a href="{!-- Pretty way to remove line breaks...
		--}{if channel_short_name=="stories"}{url_title_path="stories/view"}{/if}{!--
		--}{if channel_short_name=="photos"}{url_title_path="photos/view"}{/if}{!--
		--}{if channel_short_name=="videos"}{url_title_path="videos/view"}{/if}{!--
		--}{if channel_short_name=="interactives"}{url_title_path="interactives/view"}{/if}?utm_source=newsletter&utm_medium=email&utm_campaign={parent:url_title}"{!--
		--} style="text-decoration:none;color:#666;color:#D16F1A;font-size:40px;font-weight:300;"><?php echo $title; ?></a></SimpleURLProperty>{!--
	--}{/exp:playa:children}</td></tr>
	<tr><td class="left">
		<p><?php echo $rss; ?></p>
	</td></tr><tr><td style="text-align:right;padding:5px 0;">
	{exp:playa:children limit="1" disable="categories|category_fields|custom_fields|member_data|pagination" parse="inward"}{if no_children}{redirect="404"}{/if}
	<table style="border:none;margin:0 0 0 auto;" align="right"><tr><td><img src="{global_design_url}bracket-l.png" /></td><td style="vertical-align:middle;">{!--
		--}<SimpleURLProperty name="feature.readmore" track="true" type="plain" label="Read" href="{!-- Pretty way to remove line breaks...
		--}{if channel_short_name=="stories"}{url_title_path="stories/view"}{/if}{!--
		--}{if channel_short_name=="photos"}{url_title_path="photos/view"}{/if}{!--
		--}{if channel_short_name=="videos"}{url_title_path="videos/view"}{/if}{!--
		--}{if channel_short_name=="interactives"}{url_title_path="interactives/view"}{/if}?utm_source=newsletter&utm_medium=email&utm_campaign={parent:url_title}">
		<a href="{!-- Pretty way to remove line breaks...
		--}{if channel_short_name=="stories"}{url_title_path="stories/view"}{/if}{!--
		--}{if channel_short_name=="photos"}{url_title_path="photos/view"}{/if}{!--
		--}{if channel_short_name=="videos"}{url_title_path="videos/view"}{/if}{!--
		--}{if channel_short_name=="interactives"}{url_title_path="interactives/view"}{/if}?utm_source=newsletter&utm_medium=email&utm_campaign={parent:url_title}"{!--
		--} style="text-decoration:none;color:#2B7DD1;padding:3px 0;">Read</a></SimpleURLProperty> {!--
		--}<SimpleURLProperty name="feature.titlemore" track="true" type="plain" label="{exp:mah_eencode}{parent:title}{/exp:mah_eencode}" href="{!-- Pretty way to remove line breaks...
		--}{if channel_short_name=="stories"}{url_title_path="stories/view"}{/if}{!--
		--}{if channel_short_name=="photos"}{url_title_path="photos/view"}{/if}{!--
		--}{if channel_short_name=="videos"}{url_title_path="videos/view"}{/if}{!--
		--}{if channel_short_name=="interactives"}{url_title_path="interactives/view"}{/if}?utm_source=newsletter&utm_medium=email&utm_campaign={parent:url_title}">
		<a href="{!-- Pretty way to remove line breaks...
		--}{if channel_short_name=="stories"}{url_title_path="stories/view"}{/if}{!--
		--}{if channel_short_name=="photos"}{url_title_path="photos/view"}{/if}{!--
		--}{if channel_short_name=="videos"}{url_title_path="videos/view"}{/if}{!--
		--}{if channel_short_name=="interactives"}{url_title_path="interactives/view"}{/if}?utm_source=newsletter&utm_medium=email&utm_campaign={parent:url_title}"{!--
		--} style="text-decoration:none;color:#2B7DD1;padding:3px 0;font-weight:bold;">{parent:title}</a></SimpleURLProperty> {!--
		--}<SimpleURLProperty name="feature.onsitemore" track="true" type="plain" label="on {site_name}" href="{!-- Pretty way to remove line breaks...
		--}{if channel_short_name=="stories"}{url_title_path="stories/view"}{/if}{!--
		--}{if channel_short_name=="photos"}{url_title_path="photos/view"}{/if}{!--
		--}{if channel_short_name=="videos"}{url_title_path="videos/view"}{/if}{!--
		--}{if channel_short_name=="interactives"}{url_title_path="interactives/view"}{/if}?utm_source=newsletter&utm_medium=email&utm_campaign={parent:url_title}">
		<a href="{!-- Pretty way to remove line breaks...
		--}{if channel_short_name=="stories"}{url_title_path="stories/view"}{/if}{!--
		--}{if channel_short_name=="photos"}{url_title_path="photos/view"}{/if}{!--
		--}{if channel_short_name=="videos"}{url_title_path="videos/view"}{/if}{!--
		--}{if channel_short_name=="interactives"}{url_title_path="interactives/view"}{/if}?utm_source=newsletter&utm_medium=email&utm_campaign={parent:url_title}"{!--
		--} style="text-decoration:none;color:#2B7DD1;padding:3px 0;">on {site_name}</a></SimpleURLProperty>
		</td><td><img src="{global_design_url}bracket-r.png" style="margin-left:-4px;" /></td></tr></table>{!--
	--}{/exp:playa:children}
	</td></tr></table>
	</div></body>
</html>
[/EmailContent]
[EmailTextContent]
<text>{site_name}

<?php echo $title."\n\n";
echo str_replace(array("<br>","<br/>","<br />"),"",$rss);
?>
{exp:playa:children limit="1" disable="categories|category_fields|custom_fields|member_data|pagination" parse="inward"}{if no_children}{redirect="404"}{/if}
<SimpleURLProperty name="feature.plaintext" track="true" type="plain" label="{exp:mah_eencode}Read {parent:title} on {site_name}{/exp:mah_eencode}" href="{!-- Pretty way to remove line breaks...
	--}{if channel_short_name=="stories"}{url_title_path="stories/view"}{/if}{!--
	--}{if channel_short_name=="photos"}{url_title_path="photos/view"}{/if}{!--
	--}{if channel_short_name=="videos"}{url_title_path="videos/view"}{/if}{!--
	--}{if channel_short_name=="interactives"}{url_title_path="interactives/view"}{/if}?utm_source=newsletter&utm_medium=email&utm_campaign={parent:url_title}"/>{!--
--}{/exp:playa:children}
</text>
[/EmailTextContent]
{/exp:channel:entries}

[StyleSheet]
.body {
    background-color: #d7d2cb;
    font-family: myriad-pro, 'Myriad Pro', 'Myriad Web Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif;
    color:black;
    font-size: 16px;
    line-height: 1.25em;
}
.header {
    font-family: myriad-pro, 'Myriad Pro', 'Myriad Web Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif;
	width: 650px;
	padding: 0 10px;
	margin: 0 auto;
	text-align: left;
	border: none;
}
.left {
	text-align: left;
	padding: 5px 0;
	width: 650px;
}
.padded {
	text-align: left;
	padding: 10px 0;
	width: 650px;
}
.date{
	font-size: 0.9em;
	text-align: left;
	padding: 5px 10px 5px 0;
	color:#666;
}
.img {
	text-align:center;
	width:100%;
}
[/StyleSheet]
[/campaign]
{/exp:lk_cc:campaign}
```
