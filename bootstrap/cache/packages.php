<?php return array (
  'barryvdh/laravel-dompdf' => 
  array (
    'aliases' => 
    array (
      'PDF' => 'Barryvdh\\DomPDF\\Facade\\Pdf',
      'Pdf' => 'Barryvdh\\DomPDF\\Facade\\Pdf',
    ),
    'providers' => 
    array (
      0 => 'Barryvdh\\DomPDF\\ServiceProvider',
    ),
  ),
  'jenssegers/agent' => 
  array (
    'aliases' => 
    array (
      'Agent' => 'Jenssegers\\Agent\\Facades\\Agent',
    ),
    'providers' => 
    array (
      0 => 'Jenssegers\\Agent\\AgentServiceProvider',
    ),
  ),
  'joedixon/laravel-translation' => 
  array (
    'providers' => 
    array (
      0 => 'JoeDixon\\Translation\\TranslationServiceProvider',
      1 => 'JoeDixon\\Translation\\TranslationBindingsServiceProvider',
    ),
  ),
  'laravel-notification-channels/twilio' => 
  array (
    'providers' => 
    array (
      0 => 'NotificationChannels\\Twilio\\TwilioProvider',
    ),
  ),
  'laravel/reverb' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Reverb\\ApplicationManagerServiceProvider',
      1 => 'Laravel\\Reverb\\ReverbServiceProvider',
    ),
  ),
  'laravel/sanctum' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Sanctum\\SanctumServiceProvider',
    ),
  ),
  'laravel/tinker' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Tinker\\TinkerServiceProvider',
    ),
  ),
  'mews/purifier' => 
  array (
    'aliases' => 
    array (
      'Purifier' => 'Mews\\Purifier\\Facades\\Purifier',
    ),
    'providers' => 
    array (
      0 => 'Mews\\Purifier\\PurifierServiceProvider',
    ),
  ),
  'nesbot/carbon' => 
  array (
    'providers' => 
    array (
      0 => 'Carbon\\Laravel\\ServiceProvider',
    ),
  ),
  'nunomaduro/termwind' => 
  array (
    'providers' => 
    array (
      0 => 'Termwind\\Laravel\\TermwindServiceProvider',
    ),
  ),
  'spatie/laravel-permission' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\Permission\\PermissionServiceProvider',
    ),
  ),
);