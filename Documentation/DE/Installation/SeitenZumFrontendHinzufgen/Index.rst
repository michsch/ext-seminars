.. include:: Images.txt

.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. ==================================================
.. DEFINE SOME TEXTROLES
.. --------------------------------------------------
.. role::   underline
.. role::   typoscript(code)
.. role::   ts(typoscript)
   :class:  typoscript
.. role::   php(code)


Seiten zum Frontend hinzufügen
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Wenn Ihre Site kein Online-Anmeldungen für Veranstaltungen benutzen
soll, müssen Sie dieses Feature explizit ausschalten.

Ihre Seitenbaum sieht dann so aus:

|img-10|  *Illustration 10: Seitenstruktur für eine Installation* ohne
*Online-Anmeldung*

Usually, you’ll want to use this extension with the online
registration feature. For this, the minimal page structure will look
like this (note that you still need to set a  *sr\_feuser\_register*
if you want user self-registration):

|img-11|  *Illustration 11: Seitenstruktur für eine minimale
Installation* mit *Online-Anmeldung*

For a full-blown registration with several list views, two archive
pages, the “my events page” (where a user can see the events to which
they have signed up), registrations lists for participants and editors
and front-end editing, the page structure would look like this
(usually, you only need some of these pages):

|img-12|  *Illustration 12: Seitenstruktur einer vollständigen
Installation*

#. If you want users to be able register manually, then build up a front-
   end user system for your site. Remember which group corresponds to
   “confirmed front-end users”.

#. Add a page (which we called “ ***Events (list view)*** ” in the
   illustrations) that will contain the list view.

#. Add a page (“ ***Details (not in menu)*** ”) that will contain the
   detail view.

#. Add a “Seminar Manager”-plug-in content element to both these pages
   (from step 2 and 3) and set the corresponding types to “Event
   List”/”Event single view”. Set the content element's ”Starting Point”
   to the SysFolder that contains (or will contain) the seminar records
   (what we called “ ***Event Data*** ” in Illustrations 1-4). The
   element on “ ***Events (list view)*** ” will show the seminar list and
   the detailed seminar view will be shown on “ ***Details (not in
   menu)”*** . Usually, this content element doesn't have any access
   restrictions.If you would like to show only the seminars from certain
   organizers, put the seminar records for the organizers on separate
   pages, and add only the corresponding pages as starting pages for the
   plug-in.

#. Add a page (which we called “ ***Registration (not in menu)*** ” in
   the illustrations) that will be the registration page. Important: The
   Seminar Manager creates links to this page (for example from the list-
   and detailed view and as a redirect parameter after login) – this will
   fail if this page is access restricted. Don't hide this page and don't
   apply user restrictions to the page itself! A good way is to mark the
   page as “hide in menu”, but the page must be accessible for all
   visitors, independent of their login status (logged in or not). You
   can define restrictions on whether a user may see content elements on
   this page for each content element. Do it as described in the next
   steps.

#. To the registration page, add a text content element. Set the content
   element access to “hide at login”. Write something like this into the
   element and set the link to the front-end user login page:“Please
   <link login-page>log in first</link> before registering for a
   seminar.”If you like, you can also add a link to the front-end user
   registration page (to spare the user one click).

#. Add a second “Seminarmanager”-plug-in content element. Set the type to
   “Event Registration”. Set the content element's start pages to the
   page or pages that contain (or will contain) the seminar records. Set
   the access to *confirmed\_users\_group* .

#. Add another page that will be shown after a user has registered for an
   event. Put some thank-you message on the page. If you would like the
   single view for the event for which the user has just registered to
   display on this page, you can also add a Seminar Manager plug-in with
   the type “event list”. Set this page to “hide in the menu”.

#. Add another page that will contain the “my events” list (if you want
   to use that feature). Set the page access to “show at any login”.

#. Add a “Seminarmanager”-plug-in content element to that page and set
   the type to “My Events”. Set the content element's start pages to the
   page or pages that contain (or will contain) the seminar records. This
   element then works like the “Event List” content type, but it will
   only show those events to which the currently logged-in front-end user
   has signed up. If you want this list to show all events instead of
   current and upcoming, set “Only show events from this time-frame” to
   “all events” (you'll probably want to do this).

#. [optional] To show the countdown in the front-end, simply add a
   “Seminarmanager”-plug-in content element at the page/column where you
   want it to be shown. In the settings of this content element, just
   select “Countdown to the next event” from the “what to show” dropdown
   list.
