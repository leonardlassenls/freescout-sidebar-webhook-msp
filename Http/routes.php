<?php

Route::group(['middleware' => 'web', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\SidebarWebhook\Http\Controllers'], function () {
  Route::post('/sidebar/action', ['uses' => 'SidebarWebhookController@handleAction', 'middleware' => ['auth']])->name('sidebarwebhook.action');

  Route::get('/mailbox/sidebarwebhook/{id}', ['uses' => 'SidebarWebhookController@mailboxSettings', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('mailboxes.sidebarwebhook');
  Route::post('/mailbox/sidebarwebhook/{id}', ['uses' => 'SidebarWebhookController@mailboxSettingsSave', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('mailboxes.sidebarwebhook.save');
});
