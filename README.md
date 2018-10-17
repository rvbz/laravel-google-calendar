# laravel-google-calendar

Laravel wrapper for Google Calendar API that (unlike other solutions) utilizes the Google Client API rather than Google Service Accounts. Works seamlessly with [FullCalendar.io](http://fullcalendar.io).

- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Available Methods](#available-methods)
  - [Expose API](#expose-api)
  - [FullCalendar.io Integration](#fullcalendario-integration)

## Installation

This package can be used in Laravel 5.4 or higher.

You can install the package via composer:

``` bash
composer require hackeresq/laravel-google-calendar
```

In Laravel 5.5+ the service provider and alias will automatically get registered and you can skip this step. In older versions of the framework just add the service provider and alias in `config/app.php` file:

```php
'providers' => [
    // ...
    hackerESQ\GoogleCalendar\GoogleCalendarServiceProvider::class,
];

// ...

'aliases' => [
    // ...
    'GoogleCalendar' => hackerESQ\GoogleCalendar\Facades\GoogleCalendar::class,
];
```

You can now publish [the migration](https://github.com/hackerESQ/laravel-google-calendar/blob/master/database/migrations/update_users_table.php) and [config](https://github.com/hackerESQ/laravel-google-calendar/blob/master/config/google_calendar.php) files and migrate updates to the user table with:

```bash
php artisan vendor:publish --provider="hackerESQ\GoogleCalendar\GoogleCalendarServiceProvider" && php artisan migrate
```

<small>Note: If you are using a database that does not use the JSON field-type (e.g. older versions of MariaDB [#1]), you may get a database error while trying to run your migration. In that case, you will need to change the 'google_access_token' column to 'LONGTEXT' in the '[update_users_table.php](https://github.com/hackerESQ/laravel-google-calendar/blob/master/database/migrations/update_users_table.php)' migration file (published by the above command). Then, re-run your migration using `php artisan migrate`. </small>

<b>Success!</b> laravel-google-calendar is now installed!

## Configuration

The first step to properly configure laravel-google-calendar, you need Google API credentials. You can obtain these credentials at https://console.developers.google.com/apis/credentials/oauthclient/. 

Copy and paste the Google API credentials into your `.env` file. For example:

```reStructuredText
GOOGLE_CLIENT_ID=C13n71D_7166xlnk4q4fd24hdeteq.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=s3cr37sMnYiOk4i8fr2rX
```

## Usage

GoogleCalendar can be accessed using the easy-to-remember Facade, "GoogleCalendar."

### Available Methods

#### getAuthUrl()

Gets the interstitial Google Client API authentication URL.

> *Optional* `$redirect`: Pass a relative URL (e.g. '/dashboard') to redirect user after authenticated with Google. Defaults to site root (i.e. '/').

#### isAuthed()

Checks if currently logged in user is authenticated with Google Client API.

#### listCalendars()

Lists the available calendars for the currently logged in user.

> *Optional* `$accessToken`: Helpful when you need to manually set a user's access token (e.g. using API authentication rather than Web). Defaults to  'google_access_token' column in the `users` table. 

#### listEvents()

Lists the events for the currently logged in user.

> *Optional* `$calendarId`: Manually set Calendar ID. Defaults to 'primary' calendar.
>
> *Optional* `$accessToken`: Helpful when you need to manually set a user's access token (e.g. using API authentication rather than Web). Defaults to 'google_access_token' column in the `users` table. 
>
> *Optional* `$start`: Defaults to beginning of current month.
>
> *Optional* `$end`: Defaults to end of current month.
>
> *Optional* `$timezone`: Defaults to null.

#### updateEvent($request)

> **Required** `$request`: The request object should contain a value for 'calendarId', 'eventId', 'summary', 'location', 'description', 'allDay', 'start', and 'end'. The 'timezone' and 'attendees' are optional. 'Timezone' should be formatted according to the corresponding entry in the IANA TZDB. 'Attendees' should be a comma-separated list of emails.
>
> *Optional* `$accessToken`: Helpful when you need to manually set a user's access token (e.g. using API authentication rather than Web). Defaults to 'google_access_token' column in the `users` table. 

#### createEvent($request)

> **Required** `$request`: The request object should contain a value for 'calendarId', 'summary', 'location', 'description', 'allDay', 'start', and 'end'. The 'timezone' and 'attendees' are optional. 'Timezone' should be formatted according to the corresponding entry in the IANA TZDB. 'Attendees' should be a comma-separated list of emails.
>
> *Optional* `$accessToken`: Helpful when you need to manually set a user's access token (e.g. using API authentication rather than Web). Defaults to 'google_access_token' column in the `users` table. 

#### deleteEvent($request)

> **Required** `$request`: The request object should contain a value for 'calendarId' and 'eventId'.
>
> *Optional* `$accessToken`: Helpful when you need to manually set a user's access token (e.g. using API authentication rather than Web). Defaults to 'google_access_token' column in the `users` table. 

### Expose API

This package comes with a Controller that can be used (or you can create your own) in order to expose the above methods to an API. Here's a sample Routes definition to do this:

```php
// Google Calendar API routes
Route::group([ 'prefix'=>'api/google' ], function () {
	Route::get('calendars', '\hackerESQ\GoogleCalendar\Controllers\GoogleCalendarController@listCalendars');
	Route::get('events', '\hackerESQ\GoogleCalendar\Controllers\GoogleCalendarController@listEvents');
	Route::put('events/{id}', '\hackerESQ\GoogleCalendar\Controllers\GoogleCalendarController@updateEvent');
	Route::post('events', '\hackerESQ\GoogleCalendar\Controllers\GoogleCalendarController@createEvent');
	Route::delete('events/{id}', '\hackerESQ\GoogleCalendar\Controllers\GoogleCalendarController@deleteEvent');

});
```

These routes definitions will expose API functionality at 'api/google/calendars' and 'api/google/events' that you can consume via AJAX calls on your front-end.

### FullCalendar.io Integration

You can configure this package to input and output data in a format that can be understood by [FullCalendar.io](http://fullcalendar.io). To do so, you can modify the 'format' property in the `google_calendar.php` config file as such:

	return [
		//....
		
		'format' => 'fullcalendar',
		
		//....
	];
Then, when you initialize FullCalendar.io, you can consume the API exposed by following the steps in the [Expose API](#expose-api) section above as such:

```javascript
$('#calendar').fullCalendar({

  eventSources: [

    // your event source
    {
      url: 'api/google/events',
      type: 'GET',
      data: {
		calendar: 'primary', // define your google calendar id here
      },
      error: function() {
		alert('there was an error while fetching events!');
      }
    }

    // any other sources...

  ]

});
```

## Finally

### Contributing
Feel free to create a fork and submit a pull request if you would like to contribute.

### Bug reports
Raise an issue on GitHub if you notice something broken.

