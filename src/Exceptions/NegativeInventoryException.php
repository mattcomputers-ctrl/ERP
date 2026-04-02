<?php

namespace App\Exceptions;

class NegativeInventoryException extends \RuntimeException
{
    private string $itemCode;
    private float $onHand;
    private float $requested;

    public function __construct(string $itemCode, float $onHand, float $requested)
    {
        $this->itemCode = $itemCode;
        $this->onHand = $onHand;
        $this->requested = $requested;
        parent::__construct(
            "Cannot consume {$requested} of {$itemCode} — only {$onHand} on hand. " .
            "Shortfall: " . ($requested - $onHand)
        );
    }

    public function getItemCode(): string { return $this->itemCode; }
    public function getOnHand(): float { return $this->onHand; }
    public function getRequested(): float { return $this->requested; }
    public function getShortfall(): float { return $this->requested - $this->onHand; }
}
