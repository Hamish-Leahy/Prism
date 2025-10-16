<?php

namespace Prism\Backend\Models;

class ExpenseEstimate
{
    public string $id;
    public string $service;
    public string $serviceName;
    public float $estimatedCost;
    public string $currency;
    public ?string $location;
    public ?string $date;
    public ?int $duration;
    public ?int $participants;
    public array $metadata;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(
        string $id,
        string $service,
        string $serviceName,
        float $estimatedCost,
        string $currency = 'USD',
        ?string $location = null,
        ?string $date = null,
        ?int $duration = null,
        ?int $participants = null,
        array $metadata = []
    ) {
        $this->id = $id;
        $this->service = $service;
        $this->serviceName = $serviceName;
        $this->estimatedCost = $estimatedCost;
        $this->currency = $currency;
        $this->location = $location;
        $this->date = $date;
        $this->duration = $duration;
        $this->participants = $participants;
        $this->metadata = $metadata;
        $this->createdAt = date('c');
        $this->updatedAt = date('c');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'service' => $this->service,
            'serviceName' => $this->serviceName,
            'estimatedCost' => $this->estimatedCost,
            'currency' => $this->currency,
            'location' => $this->location,
            'date' => $this->date,
            'duration' => $this->duration,
            'participants' => $this->participants,
            'metadata' => $this->metadata,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt
        ];
    }

    public function updateEstimate(float $cost): void
    {
        $this->estimatedCost = $cost;
        $this->updatedAt = date('c');
    }

    public function addMetadata(string $key, $value): void
    {
        $this->metadata[$key] = $value;
        $this->updatedAt = date('c');
    }

    public function removeMetadata(string $key): bool
    {
        if (isset($this->metadata[$key])) {
            unset($this->metadata[$key]);
            $this->updatedAt = date('c');
            return true;
        }
        return false;
    }

    public function getCostPerPerson(): ?float
    {
        if ($this->participants && $this->participants > 0) {
            return $this->estimatedCost / $this->participants;
        }
        return null;
    }

    public function getCostPerDay(): ?float
    {
        if ($this->duration && $this->duration > 0) {
            return $this->estimatedCost / $this->duration;
        }
        return null;
    }
}
