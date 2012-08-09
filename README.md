lk_cc
=====

Constant Contact Plugin for ExpressionEngine

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
Tag Usage
----------------------
* Default_list is the lists that should be checked by default, if omitted no lists will be checked by default
* Show_list is the lists to be shown, if omitted all lists will be shown.  The lists will be shown in the order the ids are listed.
* (You can find your list IDs in the extension settings.)
* Default_list and Show_list both support list ids separated by pipes.  They do not support the use of "not".
* Email and at least one list selection will be required, the name fields are optional.  Error message strings are defined in the language file.
* First and last name fields can be omitted if desired.  This script does not currently support any other Constant Contact fields.

```html
		{exp:lk_cc:subscribe default_list="3" show_list="2|5|6|3|4"}
			{if !success}
				<form action="{path="site/subscribe"}" method="post">
					<div class="clearboth">Please confirm your subscription.</div>
					<div class="clearboth error">{error_message}</div>
					<div class="float-left"><p><sup>*</sup>Your Email:<br /><input type="text" name="email" value="{email}" /></p>
					<p>First Name:<br /> <input type="text" name="first_name" value="{first_name}" /></p>
					<p>Last Name:<br /> <input type="text" name="last_name" value="{last_name}" /></span></p></div>
					<div class="float-left"><ul class="none">
						<li>Subscribe to:</li>
						{lists}
							<li id="chk_{count}">
								<input id="chk_{count}" type="checkbox" value="{value}" name="lists[]" {checked} /> 
								<label for="chk_{count}">{name}</label>
							</li>
						{/lists}
					</ul></div>
					<div class="clearboth">
						<p><input type="submit" name="submit" value="Submit" class="contact" /></p></div>
				</form>
			{/if}
			{if success}
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
```html
	<form action="/asia/site/subscribe" target="_blank" method="post">
		<fieldset>
			<input type="hidden" name="confirm" value="confirm" />
			<label for="subscribe_email" class="screen-reader-text">Email Address</label> 
			<input type="text" value="" id="subscribe_email" name="email" />
			<input type="image" alt="Subscribe" src="/design/submit-button.png" value="Go" name="go" id="subscribe_image" />
		</fieldset>
	</form>
```