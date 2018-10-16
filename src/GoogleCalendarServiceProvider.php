<?php

namespace hackerESQ\GoogleCalendar;

use Illuminate\Support\ServiceProvider;
use Route;
use GoogleCalendar;
use Illuminate\Http\Request;

class GoogleCalendarServiceProvider extends ServiceProvider {

	protected $defer = false;

	/**
     * Define routes
     *
     * @return void
     */
    public function boot()
    {
    	/**
		 * Google API OAuth2 routes
		 */
        Route::group(['middleware' => ['web', 'auth'] ] , function () {

			Route::get('/oauth2callback', function (Request $request) {
				GoogleCalendar::oauth2callback($request);

				// If redirect URL is set, redirect to that URL. 
				return redirect()->to($request->has('state') ? $request->state : '/');
				
			});

			Route::get('/revoke_google_token', function (Request $request) {
				GoogleCalendar::revokeToken();

				// If redirect URL is set, redirect to that URL. 
				return $request->has('redirect') ?
					redirect()->to($request->redirect) :
					redirect()->back();

			})->name('revoke_google_token');

		});

		/**
         * Publish google calendar config file
         */
        $this->publishes([
            __DIR__ . '/../../config/google_calendar.php' => config_path('google_calendar.php'),
        ], 'config');


        /**
         * Publish google calendar migration
         */
        $timestamp = date('Y_m_d_His', time());

        $this->publishes([
            __DIR__.'/../../database/migrations/update_users_table.php' => $this->app->databasePath()."/migrations/{$timestamp}_update_users_table.php",
        ], 'migrations');
    }


	/**
     * Register services.
     *
     * @return void
     */
	public function register () {


		$this->app->singleton('GoogleCalendar','hackerESQ\GoogleCalendar\GoogleCalendar'); 



	}



}