# ProcessProcesspr

```php
$processes = [
    $process = new Process(['ls', '-lsa']),
    $process = new Process(['ls', '-lsa']),
    $process = new Process(['ls', '-lsa']),
    $process = new Process(['ls', '-lsa']),
];
(new QueuedProcessor())->wait($processes, 2);
```