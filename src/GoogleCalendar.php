<?php
 
namespace hackerESQ\GoogleCalendar;
 
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Illuminate\Support\Carbon;

class GoogleCalendar
{

    public function __construct() {
        // TODO: Pass auth'd user through construct method.
        $this->client = new Google_Client();
        $this->client->setApplicationName(config('app.name'));
        $this->client->setClientId(config('google_calendar.client_id'));
        $this->client->setClientSecret(config('google_calendar.client_secret'));
        $this->client->addScope(Google_Service_Calendar::CALENDAR);
        $this->client->setAccessType("offline");
        $this->client->setIncludeGrantedScopes(true);
        $this->client->setApprovalPrompt('force');
        $this->client->setRedirectUri(url('/')."/oauth2callback");
    }

    public function getAuthUrl($redirect = NULL) {
         
        // Set redirect URL as state so it gets passed back with the OAuth2 callback
        !empty($redirect) ? $this->client->setState($redirect) : NULL;
 
        return filter_var($this->client->createAuthUrl(), FILTER_SANITIZE_URL);
 
    }

    public function isAuthed() {
 
        // Have we set the session yet? If so, great, let's use this access token!
        if (session('google_access_token') && session()->has('google_access_token')) {

            return $this->checkToken(session("google_access_token"));
 
        } 

        // Laravel session may have expired. Let's check the DB. Does the user have an access token in the DB?
        if (!auth()->user()->google_connected || empty(auth()->user()->google_access_token)) {
            return false;
        }

        // Hmm, looks like they have an access token in the DB, but we don't have a session.
        // Let's grab the access token from the DB and set the Laravel session.
        $accessToken = auth()->user()->google_access_token;

        if ($this->checkToken($accessToken)) {
            session(["google_access_token" => $accessToken]);
            return true;
        } else {
            return false;
        }
 
    }

    public function checkToken($accessToken) {

        $this->client->setAccessToken($accessToken);

        // Let's check if the access token is expired.
        if ($this->client->isAccessTokenExpired()) {
            
            $accessToken = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());

            session(["google_access_token" => $accessToken]);
            $user = auth()->user();
            $user->setAttribute('google_access_token',json_encode($accessToken));
            $user->save();
        }

        return $this->client->getAccessToken() ? true : false;
    }

    public function oauth2callback($request) {
 
        if ($request->code) {

            $this->client->authenticate($request->code);

            $accessToken = $this->client->getAccessToken();

            // Save to session and database
            session(['google_access_token' => $accessToken]);
            $user = auth()->user();
            $user->setAttribute('google_access_token',json_encode($accessToken));
            $user->setAttribute('google_connected',true);
            $user->save();
 
            return true;
             
        }
 
        return false;
 
    }

    public function revokeToken() {

        $this->client->revokeToken();

        session(['google_access_token' => NULL]);
        $user = auth()->user();
        $user->setAttribute('google_access_token',null);
        $user->setAttribute('google_connected',false);
        $user->save();

        return true;

    }

    public function listCalendars($accessToken = NULL) {

        // Set defaults
        $accessToken = empty($accessToken) ? auth()->user()->google_access_token : $accessToken;

        !$this->isAuthed() ? abort(401, 'Access token required') : NULL;

        $this->client->setAccessToken($accessToken);

        $service = new Google_Service_Calendar($this->client); 

        // List all available calendars for user.
        return $service->calendarList->listCalendarList()->getItems();

    }

    public function listEvents($calendarId = NULL, $accessToken = NULL, $start = NULL, $end = NULL, $timezone = NULL) {

        // Set defaults
        $calendarId = empty($calendarId) ? 'primary' : $calendarId;
        $start = empty($start) ? Carbon::now()->startOfMonth() : $start;
        $end = empty($end) ? Carbon::now()->endOfMonth() : $end;
        $accessToken = empty($accessToken) ? auth()->user()->google_access_token : $accessToken;

        !$this->isAuthed() ? abort(401, 'Access token required') : NULL;

        $this->client->setAccessToken($accessToken);

        $service = new Google_Service_Calendar($this->client);

        $optParams = array(
            // 'maxResults' => 10,
            // 'q' => 'search or query',
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => Carbon::parse($start)->toIso8601String(),
            'timeMax' => Carbon::parse($end)->toIso8601String(),
            'timeZone' => !empty($timezone) ? $timezone : NULL
        );

        // Get list of all events
        $results = $service->events->listEvents($calendarId, $optParams) ;
        $events = $results->getItems();

        // Format for FullCalendar.io?
        if (config('google_calendar.format') == 'fullcalendar') {
            $fullcalendar = [];
            foreach ($events as $event) {

                // We can determine if an event is all day by checking if it has "date" rather than "datetime"
                $isAllDay = ($event->getStart()->getDate() !="" ? true : false);

                $fullcalendar[] = array(
                    'id'=>$event->getId(),
                    'title'=>$event->getSummary(),
                    'summary'=>$event->getSummary(),
                    'start'=>($isAllDay) ? $event->getStart()->getDate() : $event->getStart()->getDateTime(),
                    'end'=>($isAllDay) ? $event->getEnd()->getDate() : $event->getEnd()->getDateTime(),
                    'timezone'=>$event->getStart()->getTimeZone(),
                    'allDay'=>$isAllDay,
                    'description'=>$event->getDescription(),
                    'location'=>$event->getLocation(),
                    'recurrence'=>$event->getRecurrence(),
                    'recurringEventId'=>$event->getRecurringEventId(),
                    'attendees'=>$event->getAttendees(),
                    'htmlLink'=>$event->getHtmlLink(),
                    'hangoutLink'=>$event->getHangoutLink(),
                    'created_at'=>$event->getCreated(),
                    'updated_at'=>$event->getUpdated(),
                    );
            }

            return $fullcalendar;

        } else {
            
            return $events;

        }
                
    }

    public function updateEvent($request, $accessToken = NULL) {

        // Set defaults
        $accessToken = empty($accessToken) ? auth()->user()->google_access_token : $accessToken;

        !$this->isAuthed() ? abort(401, 'Access token required') : NULL;

        $this->client->setAccessToken($accessToken);

        $service = new Google_Service_Calendar($this->client);

        // Find the event we're updating (TODO: If changing the calendar ID we need to use "move")
        // see: https://developers.google.com/calendar/v3/reference/events/move
        $event = $service->events->get($request->calendarId, $request->eventId);

        // Update basic details of event
        $event->setSummary($request->summary);
        $event->setLocation($request->location);
        $event->setDescription($request->description);

        // Update time and date for event
        if ($request->allDay=='true' && $request->end!="") {            // pure all day event

            $event->start->dateTime = null;
            $event->end->dateTime = null;

            $event->start->date = Carbon::parse($request->start, $request->timezone)->timezone('UTC')->format('Y-m-d');
            $event->end->date = Carbon::parse($request->end, $request->timezone)->timezone('UTC')->format('Y-m-d');

        } elseif ($request->allDay=='false' && $request->end=="") {     // changed from all day to time-limited
            
            $event->start->date = null;
            $event->end->date = null;

            $event->start->dateTime = Carbon::parse($request->start, $request->timezone)->timezone('UTC')->toIso8601String();
            $event->end->dateTime = Carbon::parse($request->start, $request->timezone)->addHour()->timezone('UTC')->toIso8601String();

        } elseif ($request->allDay=='true' && $request->end=="") {      // changed from time-limited to all day

            $event->start->dateTime = null;
            $event->end->dateTime = null;

            $event->start->date = Carbon::parse($request->start, $request->timezone)->timezone('UTC')->format('Y-m-d');
            $event->end->date = Carbon::parse($request->start, $request->timezone)->addDay()->timezone('UTC')->format('Y-m-d');

        } else {                                                        // nothing funky about this one

            $event->start->dateTime = Carbon::parse($request->start, $request->timezone)->timezone('UTC')->toIso8601String();
            $event->end->dateTime = Carbon::parse($request->end, $request->timezone)->timezone('UTC')->toIso8601String();

        }

        // Update timezone for event
        $event->start->timeZone = $request->timezone;
        $event->end->timeZone = $request->timezone;

        // Update event's attendees
        if (!empty($request->attendees)) {
            $event->attendees = [];
            $attendees = explode(',',$request->attendees);
            foreach ($attendees as $attendee) {
                array_push( $event->attendees, array ('email'=>$attendee) );
            }
        }

        // Update event
        $updatedEvent = $service->events->update($request->calendarId, $event->getId(), $event);

        return array('message' => 'success');

    }

    public function createEvent($request, $accessToken = NULL) {

        // Set defaults
        $accessToken = empty($accessToken) ? auth()->user()->google_access_token : $accessToken;
        
        !$this->isAuthed() ? abort(401, 'Access token required') : NULL;

        $this->client->setAccessToken($accessToken);

        $service = new Google_Service_Calendar($this->client);

        if ($request->allDay=='true') {                                 // all day
            $start = array(
                'date' => Carbon::parse($request->start, $request->timezone)->timezone('UTC')->format('Y-m-d'),
                'timezone' => !empty($request->timezone) ? $request->timezone : NULL
            );
            $end = array( 
                'date' => Carbon::parse($request->end, $request->timezone)->timezone('UTC')->format('Y-m-d'),
                'timezone' => !empty($request->timezone) ? $request->timezone : NULL
            );
        } else {                                                        // time limited
            $start = array( 
                'dateTime' => Carbon::parse($request->start, $request->timezone)->timezone('UTC')->toIso8601String(),
                'timezone' => !empty($request->timezone) ? $request->timezone : NULL
            );
            $end = array( 
                'dateTime' => Carbon::parse($request->end, $request->timezone)->timezone('UTC')->toIso8601String(),
                'timezone' => !empty($request->timezone) ? $request->timezone : NULL
            );
        }
        
        // Create event object
        $event = new Google_Service_Calendar_Event(array(
            'summary' => $request->summary,
            'location' => $request->location,
            'description' => $request->description,
            'start' => $start,
            'end' => $end,
            // 'recurrence' => array(
            //     'RRULE:FREQ=DAILY;COUNT=2'
            // ),
            // 'reminders' => array(
            //     'useDefault' => FALSE,
            //     'overrides' => array(
            //         array('method' => 'email', 'minutes' => 24 * 60),
            //         array('method' => 'popup', 'minutes' => 10),
            //     ),
            // ),
        ));

        // Create event's attendees
        if (!empty($request->attendees)) {
            $event->attendees = [];
            $attendees = explode(',',$request->attendees);
            foreach ($attendees as $attendee) {
                array_push( $event->attendees, array ('email'=>$attendee) );
            }
        }

        // Insert new event
        $event = $service->events->insert($request->calendarId, $event);

        return array('message' => 'success' , 'event' => $event);

    }

    public function deleteEvent($request, $accessToken = NULL) {

        // Set defaults
        $accessToken = empty($accessToken) ? auth()->user()->google_access_token : $accessToken;
        
        !$this->isAuthed() ? abort(401, 'Access token required') : NULL;

        $this->client->setAccessToken($accessToken);

        $service = new Google_Service_Calendar($this->client);

        // Delete event
        $service->events->delete($request->calendarId, $request->id);

        return array('message' => 'success');

    }

}