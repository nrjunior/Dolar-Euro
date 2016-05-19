<?php
// Routes

$app->get('/', App\Action\HomeAction::class)->setName('homepage');

$app->get('/cotacoes/dolar', App\Action\DolarAction::class)->setName('cotacoes.dolar');

$app->get('/cotacoes/euro', App\Action\EuroAction::class)->setName('cotacoes.euro');