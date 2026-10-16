<?php

declare(strict_types=1);

/**
 * Web Routes
 * Format: [METHOD, PATH, CONTROLLER, ACTION, MIDDLEWARE[]]
 * Specific paths MUST be registered before parameterized {id} routes.
 */

return [
    // Auth
    ['GET',  '/login',  'AuthController', 'showLogin', ['guest']],
    ['POST', '/login',  'AuthController', 'login',     ['guest', 'csrf']],
    ['GET',  '/register',  'AuthController', 'showRegister', ['guest']],
    ['POST', '/register',  'AuthController', 'register',     ['guest', 'csrf']],
    ['POST', '/logout', 'AuthController', 'logout',    ['auth', 'csrf']],
    ['GET',  '/logout', 'AuthController', 'logout',    ['auth']],

    // Dashboard
    ['GET', '/',          'DashboardController', 'index', ['auth']],
    ['GET', '/dashboard', 'DashboardController', 'index', ['auth']],

    // Posts — static paths before {id}
    ['GET',  '/posts',                    'PostController', 'index',          ['auth']],
    ['GET',  '/posts/create',             'PostController', 'create',         ['auth']],
    ['POST', '/posts',                    'PostController', 'store',          ['auth', 'csrf']],
    ['POST', '/posts/suggest-topics',     'PostController', 'suggestTopics',  ['auth', 'csrf']],
    ['GET',  '/posts/suggest-topics',     'PostController', 'suggestTopics',  ['auth']],

    // Posts — parameterized
    ['GET',  '/posts/{id}',               'PostController', 'show',           ['auth']],
    ['GET',  '/posts/{id}/edit',          'PostController', 'edit',           ['auth']],
    ['POST', '/posts/{id}',               'PostController', 'update',         ['auth', 'csrf']],
    ['GET',  '/posts/{id}/generate',      'PostController', 'generate',       ['auth']],
    ['POST', '/posts/{id}/generate',      'PostController', 'generate',       ['auth', 'csrf']],
    ['POST', '/posts/{id}/review',        'PostController', 'review',         ['auth', 'csrf']],
    ['POST', '/posts/{id}/approve',       'PostController', 'approve',        ['auth', 'csrf']],
    ['POST', '/posts/{id}/reject',        'PostController', 'reject',         ['auth', 'csrf']],
    ['POST', '/posts/{id}/schedule',      'PostController', 'schedule',       ['auth', 'csrf']],
    ['POST', '/posts/{id}/publish-now',   'PostController', 'publishNow',     ['auth', 'csrf']],
    ['GET',  '/posts/{id}/publish-now',   'PostController', 'publishNow',     ['auth']],
    ['POST', '/posts/{id}/upload-image',   'PostController', 'uploadImage',    ['auth', 'csrf']],
    ['GET',  '/posts/{id}/images',         'PostController', 'getImages',      ['auth']],
    ['POST', '/posts/{id}/replace-images', 'PostController', 'replaceImages',  ['auth', 'csrf']],
    ['POST', '/posts/{id}/optimize-seo',   'PostController', 'optimizeSeo',    ['auth', 'csrf']],

    // Sources
    ['GET',  '/sources',                               'SourceController', 'index',                  ['auth']],
    ['POST', '/sources',                               'SourceController', 'store',                  ['auth', 'csrf']],
    ['POST', '/sources/fetch',                         'SourceController', 'fetch',                  ['auth', 'csrf']],
    ['POST', '/sources/suggest',                       'SourceController', 'suggest',                ['auth', 'csrf']],
    ['POST', '/sources/blueprint',                     'SourceController', 'saveBlueprint',          ['auth', 'csrf']],
    ['POST', '/sources/blueprint/generate',            'SourceController', 'generateBlueprintIdeas', ['auth', 'csrf']],
    ['GET',  '/sources/blueprint/ideas',               'SourceController', 'getBlueprintIdeas',      ['auth']],
    ['POST', '/sources/blueprint/ideas/{id}/dismiss',  'SourceController', 'dismissBlueprintIdea',   ['auth', 'csrf']],
    ['POST', '/sources/{id}/delete',                   'SourceController', 'delete',                 ['auth', 'csrf']],
    ['POST', '/sources/suggestions/{id}/accept',       'SourceController', 'acceptSuggestion',       ['auth', 'csrf']],
    ['POST', '/sources/suggestions/{id}/reject',       'SourceController', 'rejectSuggestion',       ['auth', 'csrf']],

    // Chat
    ['GET',  '/chat',        'ChatController', 'index', ['auth']],
    ['POST', '/chat',        'ChatController', 'send',  ['auth', 'csrf']],
    ['POST', '/chat/clear',  'ChatController', 'clear', ['auth', 'csrf']],

    // Users (multi-account)
    ['GET',  '/users',            'UserController', 'index',  ['auth']],
    ['POST', '/users',            'UserController', 'store',  ['auth', 'csrf']],
    ['POST', '/users/{id}/toggle','UserController', 'toggle', ['auth', 'csrf']],

    // Blogger
    ['GET',  '/blogger',              'BloggerController', 'index',      ['auth']],
    ['GET',  '/blogger/connect',      'BloggerController', 'connect',    ['auth']],
    ['GET',  '/auth/google/callback', 'BloggerController', 'callback',   ['auth']],
    ['POST', '/blogger/disconnect',   'BloggerController', 'disconnect', ['auth', 'csrf']],
    ['POST', '/blogger/test',         'BloggerController', 'test',       ['auth', 'csrf']],
    ['GET',  '/blogger/test',         'BloggerController', 'test',       ['auth']],

    // Settings & AI Providers
    ['GET',  '/settings',          'SettingsController', 'index',            ['auth']],
    ['POST', '/settings',          'SettingsController', 'update',           ['auth', 'csrf']],
    ['GET',  '/ai-usage',          'SettingsController', 'aiUsage',          ['auth']],
    ['POST', '/ai-usage/toggle',   'SettingsController', 'toggleProvider',   ['auth', 'csrf']],
    ['POST', '/ai-usage/reorder',  'SettingsController', 'reorderProviders',  ['auth', 'csrf']],
    ['GET',  '/activity-logs',     'ActivityController', 'index',            ['auth']],
];
