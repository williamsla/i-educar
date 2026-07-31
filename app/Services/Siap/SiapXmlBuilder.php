<?php

namespace App\Services\Siap;

use SimpleXMLElement;

class SiapXmlBuilder
{
    private SimpleXMLElement $xml;

    public function __construct(
        private readonly string $codigo,
        private readonly string $exercicio,
        private readonly string $mes,
    ) {
        $this->xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><SIAP/>');
        $this->xml->addChild('Codigo', $this->sanitize($codigo));
        $this->xml->addChild('Exercicio', $this->sanitize($exercicio));
        $this->xml->addChild('Mes', $this->sanitize((string) (int) $mes));
    }

    public function addRecord(string $elementName, array $fields): SimpleXMLElement
    {
        $node = $this->xml->addChild($elementName);

        foreach ($fields as $name => $value) {
            $node->addChild($name, $this->escape((string) ($value ?? '')));
        }

        return $node;
    }

    public function toFormattedXml(): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($this->xml->asXML());

        return $dom->saveXML();
    }

    public function getXml(): SimpleXMLElement
    {
        return $this->xml;
    }

    private function sanitize(?string $value): string
    {
        return trim((string) $value);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
