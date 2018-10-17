<?php

return [

	/*
    |--------------------------------------------------------------------------
    | Google OAuth2 API Client Credentials
    |--------------------------------------------------------------------------
    |
    | Set your Google OAuth2 API Client Credentials in your .env file. These credentials are
    | obtained here: https://console.developers.google.com/apis/credentials/oauthclient/
    |
    */

	'client_id' => env('GOOGLE_CLIENT_ID'),
	'client_secret' => env('GOOGLE_CLIENT_SECRET'),

	/*
    |--------------------------------------------------------------------------
    | Format of the input and output of event and calendar data
    |--------------------------------------------------------------------------
    |
    | Currently, the available settings are 'null' which results in Google Calendar API
    | formatted data (see Google Calendar API documentation for more information). Or
    | you can use 'fullcalendar' which formats the data for FullCalendar.io.
    |
    */

	'format' => null,

];
