<?php declare(strict_types = 1);

namespace x3wil\MachineLearning;

class Prediction
{

    private $results = [];

    public function __construct(array $results)
    {
        asort($results);
        $this->results = $results;
    }

    public function getBestMatch(): string
    {
        return array_search(max($this->getProbabilities()), $this->getProbabilities(), true);
    }

    public function getProbabilities(): array
    {
        return $this->results;
    }

    public function getNormalizedProbabilities(): array
    {
        $max = max($this->getProbabilities());
        $min = min($this->getProbabilities());

        if ($min === $max) {
            return [];
        }

        $normalized = [];
        foreach ($this->getProbabilities() as $key => $probability) {
            $normalized[$key] = (((100 - 1) * ($probability - $min)) / ($max - $min)) + 1;
        }

        arsort($normalized);

        return $normalized;
    }

}
