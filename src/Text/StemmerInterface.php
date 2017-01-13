<?php declare(strict_types = 1);

namespace x3wil\MachineLearning\Text;

interface StemmerInterface
{

    public function stemm(string $word): string;

}