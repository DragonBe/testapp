<?php
require_once '/Users/dragonbe/workspace/Faker/src/autoload.php';

$max = 10;
if (1 < $argc) {
    $max = $argv[1];
}

$faker = \Faker\Factory::create();
for ($i = 0; $i < 100; $i++) {
    echo sprintf(
        'INSERT INTO `account` (`github_id`, `login`, `name`, `email`, `avatar_url`, `html_url`, `company`, `location`) '
        . 'VALUES (%d, \'%s\', \'%s\', \'%s\', \'%s\', \'%s\', \'%s\', \'%s\');',
        rand(100000, 999999),
        $faker->username,
        addslashes($faker->name),
        $faker->email,
        sprintf('http://www.gravatar.com/avatar/%s.jpg', md5($faker->email)),
        $faker->url,
        $faker->company,
        $faker->country
    ) . PHP_EOL;
}
for ($i = 0; $i < 20; $i++) {
    echo sprintf(
        'INSERT INTO `account_tags` VALUES (%d, %d);',
        rand(1, $max),
        rand(1, 5)
    ) . PHP_EOL;
}