<?php 

namespace hackerESQ\GoogleCalendar\Controllers;

use Illuminate\Http\Request;
use GoogleCalendar;
use App\Http\Controllers\Controller;


class GoogleCalendarController extends Controller
{

	public function listCalendars () {

		return GoogleCalendar::listCalendars(auth()->guard('api')->user()->google_access_token);

	}

	public function listEvents (Request $request) {

		return GoogleCalendar::listEvents(
			$request->calendar,
			auth()->guard('api')->user()->google_access_token,
			$request->start,
			$request->end,
			$request->timezone
		);

	}

	public function updateEvent (Request $request) {

		return GoogleCalendar::updateEvent($request, auth()->guard('api')->user()->google_access_token);

	}

	public function createEvent (Request $request) {

		return GoogleCalendar::createEvent($request, auth()->guard('api')->user()->google_access_token);

	}

	public function deleteEvent (Request $request) {

		return GoogleCalendar::deleteEvent($request, auth()->guard('api')->user()->google_access_token);

	}





}