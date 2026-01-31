#Views Attachments as Tabs

Views Attachments as Tabs is a module that allows you to display a Views
attachment as a tab on a display that supports attachments.

##Instructions

1. Enable the module in the admin interface.
2. Either enable the Views Attachments as Tabs Olivero module or write a
theme hook, template_preprocess_views_view_attachment_tabs() to ensure
that the output of this module will conform to the markup needed for tabs
as is required by your theme. (You may have a theme that does not require
this).
3. Go to Structure > Views > Settings > Advanced, and check the box,
"Views attachment tabs" under the "Display Extenders" section.
4. Create a view that has a display that accepts attachments (e.g. block
or page).
5. In the middle column of the View edit form, there is a section titled
"Attachment tabs"; click the link within that section, check the box
to enable, and give your tab a title (then click "Apply").
6. Create an attachment for the view, and set the parent for the attachment.
7. Click the link by "Attach as tab" to enable the attachment as a tab and
give it a title (then click "Apply").
8. Repeat steps 6 and 7 as needed, then save your view.

##Credits
Work created by @nikathone and @andileco on behalf of JSI:
https://www.drupal.org/john-snow-inc-jsi
