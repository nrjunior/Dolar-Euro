<?php
namespace App\Action;

use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use phpQuery;
use Carbon\Carbon;
use Thapp\XmlBuilder\XMLBuilder;
use Thapp\XmlBuilder\Normalizer;
use FileSystemCache;
use Stringy\Stringy as S;

final class DolarAction
{
    private $view;
    private $logger;

    public function __construct(Twig $view, LoggerInterface $logger)
    {
        $this->view = $view;
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response, $args)
    {
        $cache_name = (string)S::create(substr(__CLASS__, 11, -6))->toLowerCase();
        FileSystemCache::$cacheDir = __DIR__ . '/../../../cache/tmp';
        $key = FileSystemCache::generateCacheKey('cache-' . $cache_name, null);
        $data = FileSystemCache::retrieve($key);

        if($data === false)
        {
            $doc = phpQuery::newDocumentFileHTML('http://economia.uol.com.br/cotacoes/');
            $doc->find('head')->remove();
            $doc->find('meta')->remove();
            $doc->find('noscript')->remove();
            $doc->find('script')->remove();
            $doc->find('style')->remove();
            $doc->find('path')->remove();
            $doc->find('svg')->remove();
            $doc->find('footer')->remove();

            $html = pq('body');
            
            $data = array(
                'quotation' => $this->processQuotation($html),
                'info' => array(
                    'title' => 'Dolar',
                    'createdat' => Carbon::now('America/Sao_Paulo')->toDateTimeString()
                ),
                'publishedat' => $this->processInfo($html),
            );

            FileSystemCache::store($key, $data, 1800);
        }

        $xmlBuilder = new XMLBuilder('root');
        $xmlBuilder->load($data);
        $xml_output = $xmlBuilder->createXML(true);
        $response->write($xml_output);
        $response = $response->withHeader('content-type', 'text/xml');
        return $response;
    }

    public function processInfo($html)
    {
        $doc = phpQuery::newDocument($html);

        $date_published = $doc['section.mod-cotacoes:eq(0) caption.titulo span:eq(0)']->text();
        $hour = str_replace('h', ':', substr($date_published, -5)) . ':00';
        $date = implode('-',array_reverse(explode('/',substr($date_published, 0, 10))));

        $date_hour = strtotime($date . ' ' . $hour);

        return 'Atualizado em ' . date('d/m', $date_hour) . ' ás ' . date('H:i', $date_hour) . 'h';
    }

    public function processQuotation($html)
    {
        $data = array();
        $doc = phpQuery::newDocument($html);

        return array(
            'buy' => $doc['section.mod-cotacoes:eq(0) table tbody tr:eq(0) td:eq(1)' ]->text(),
            'sell' => $doc['section.mod-cotacoes:eq(0) table tbody tr:eq(0) td:eq(2)']->text(),
            'variation' => str_replace(',', '.', substr(trim($doc['section.mod-cotacoes:eq(0) table tbody tr:eq(0) td:eq(3)']->text()),0 , -1))
        );
    }
}
