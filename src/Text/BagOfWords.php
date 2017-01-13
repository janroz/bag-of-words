<?php declare(strict_types = 1);

namespace x3wil\MachineLearning\Text;

use x3wil\MachineLearning\Prediction;

class BagOfWords implements \Serializable
{

    /** @var int */
    private $nGrams = 1;

    /** @var array */
    private $documents = [];

    /** @var int */
    private $vocabularyCount = 0;

    /** @var array */
    private $tokens = [];

    /** @var int */
    private $documentsSum = 0;

    /** @var string[] */
    private $stopWords = [];

    /** @var \x3wil\MachineLearning\Text\StemmerInterface */
    private $stemmer;

    /**
     * @param string[] $stopWords
     */
    public function setStopWords(array $stopWords)
    {
        $this->stopWords = array_flip(array_map(function (string $word): string {
            return trim($word);
        }, $stopWords));
    }

    public function setStemmer(StemmerInterface $stemmer)
    {
        $this->stemmer = $stemmer;
    }

    public function setNgrams(int $number)
    {
        if ($number < 1) {
            // @todo throw
        }
        $this->nGrams = $number;
    }

    public function add(string $class, string $document)
    {
        if (!isset($this->documents[$class])) {
            $this->documents[$class] = [];
        }
        $this->documents[$class][] = $document;
        $this->documentsSum++;
    }

    public function train(callable $callback = null)
    {
        $vocabulary = [];
        foreach ($this->documents as $class => $documents) {
            $this->tokens[$class] = [
                'total' => 0,
                'documents' => 0,
                'frequency' => [],
            ];

            foreach ($documents as $document) {
                $tokens = $this->tokenize($document);
                $this->tokens[$class]['total'] += count($tokens);
                $this->tokens[$class]['documents'] += 1;

                foreach ($tokens as $token) {
                    if (!isset($vocabulary[$token])) {
                        $vocabulary[$token] = true;
                    }

                    if (!isset($this->tokens[$class]['frequency'][$token])) {
                        $this->tokens[$class]['frequency'][$token] = 0;
                    }

                    $this->tokens[$class]['frequency'][$token]++;
                }

                if ($callback !== null) {
                    $callback();
                }
            }
        }
        $this->vocabularyCount = count($vocabulary);
    }

    public function predict(string $text): Prediction
    {
        $tokens = $this->tokenize($text);
        $probabilities = [];

        foreach ($this->tokens as $class => $data) {
            $probability = $this->calculatePriorProbability($class);

            foreach ($tokens as $token) {
                $probability += $this->calculateProbability($class, $token);
            }

            $probabilities[$class] = $probability;
        }

        return new Prediction($probabilities);
    }

    private function calculateProbability(string $class, string $token): float
    {
        $frequency = $this->tokens[$class]['frequency'][$token] ?? 0;

        return log(($frequency + 0.0000000000001) / ($this->tokens[$class]['total'] * $this->vocabularyCount));
    }

    private function calculatePriorProbability(string $class): float
    {
        return log($this->tokens[$class]['documents'] / $this->documentsSum);
    }

    private function tokenize(string $document): array
    {
        $tokens = [];
        foreach (explode(' ', $this->cleanDocument($document)) as $token) {
            $token = trim($token);
            if ($this->isStopWord($token)) {
                continue;
            }

            $tokens[] = $this->stemmer !== null ? $this->stemmer->stemm($token) : $token;
        }

        $tokensSimple = $tokens;
        for ($maxWords = 2; $maxWords <= $this->nGrams; $maxWords++) {
            $tokenCount = count($tokensSimple);
            for ($i = 0; $i < $tokenCount; $i++) {
                $phrase = [];
                for ($j = 0; $j < $this->nGrams; $j++) {
                    if (!isset($tokensSimple[$j + $i])) {
                        break;
                    }
                    $phrase[] = $tokensSimple[$j + $i];
                }

                if (count($phrase) === $maxWords) {
                    $tokens[] = implode(' ', $phrase);
                }
            }
        }

        return $tokens;
    }

    private function cleanDocument(string $document): string
    {
        $document = strip_tags($document);
        $document = html_entity_decode($document);
        $document = preg_replace('~\PL~u', ' ', $document);

        return mb_strtolower($document);
    }

    private function isStopWord(string $token): bool
    {
        return $token === '' || isset($this->stopWords[$token]) || mb_strlen($token) < 3;
    }

    /**
     * @param float $minimalFrequency In range 0-1
     * @return string[]
     */
    public function getRepetitiveWords(float $minimalFrequency = 0.0): array
    {
        if (count($this->documents) === 0) {
            return [];
        }

        $frequencies = [];
        foreach ($this->documents as $class => $documents) {
            $frequencies[$class] = [];

            foreach ($documents as $document) {
                foreach (explode(' ', $this->cleanDocument($document)) as $token) {
                    $token = trim($token);
                    if ($this->isStopWord($token)) {
                        continue;
                    }

                    if (!isset($frequencies[$class][$token])) {
                        $frequencies[$class][$token] = 0;
                    }
                    $frequencies[$class][$token]++;
                }
            }
        }

        $normalized = [];
        foreach ($frequencies as $class => $tokens) {
            arsort($tokens);

            $min = end($tokens);
            $max = reset($tokens);

            $normalized[$class] = [];
            foreach ($tokens as $token => $frequency) {
                if (($max - $min) > 0) {
                    $normalized[$class][$token] = (((1 - 0.1) * ($frequency - $min)) / ($max - $min)) + 0.1;
                } else {
                    $normalized[$class][$token] = 1;
                }
            }
        }

        $normalized[] = function (float $v1, float $v2) use ($minimalFrequency): int {
            if ($v1 >= $minimalFrequency && $v2 >= $minimalFrequency) {
                return 0;
            } elseif ($v1 < $minimalFrequency && $v2 < $minimalFrequency) {
                return -1;
            }

            return 1;
        };

        return call_user_func_array('array_uintersect_assoc', $normalized);
    }

    public function serialize(): string
    {
        return serialize([
            $this->nGrams,
            $this->vocabularyCount,
            $this->tokens,
            $this->documentsSum,
            $this->stopWords,
        ]);
    }

    public function unserialize($data)
    {
        list(
            $this->nGrams,
            $this->vocabularyCount,
            $this->tokens,
            $this->documentsSum,
            $this->stopWords
            ) = unserialize($data);
    }

}
