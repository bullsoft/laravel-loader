# laravel-loader
奇怪的需求产生奇怪的方案，本方案就是。。。

Load Laravel-Application in your codebase.

```php
$conf = [
    'basePath' => '/path/to/your/laravel-app',
    'url' => 'http://redirect/to/when/login/successfully',
];

$laravel = \BullSoft\Loader\Laravel::bootstrap($conf['basePath']);

if(!$laravel->checkUserLogin()) {
    $info = ['username' => 'roy', 'password' => 'iloveroy'];
    $laravel->login($info, $conf['url']); // use null for second param when you do not want to redirect
}

$request  = $laravel->newApiRequest('/api/v1/user/index', 'GET');
$response = $laravel->sendRequest($request);
$content  = $response->getContent();
$laravel->finish($request, $response);

echo $content;
```
