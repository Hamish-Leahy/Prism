<?php

namespace Prism\Backend\Models;

class Expense
{
    public string $id;
    public string $budgetId;
    public string $categoryId;
    public ?string $subcategoryId;
    public float $amount;
    public string $description;
    public string $date;
    public array $tags;
    public bool $isRecurring;
    public ?string $recurringFrequency;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(
        string $id,
        string $budgetId,
        string $categoryId,
        float $amount,
        string $description,
        string $date,
        array $tags = [],
        bool $isRecurring = false,
        ?string $recurringFrequency = null,
        ?string $subcategoryId = null
    ) {
        $this->id = $id;
        $this->budgetId = $budgetId;
        $this->categoryId = $categoryId;
        $this->subcategoryId = $subcategoryId;
        $this->amount = $amount;
        $this->description = $description;
        $this->date = $date;
        $this->tags = $tags;
        $this->isRecurring = $isRecurring;
        $this->recurringFrequency = $recurringFrequency;
        $this->createdAt = date('c');
        $this->updatedAt = date('c');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'budgetId' => $this->budgetId,
            'categoryId' => $this->categoryId,
            'subcategoryId' => $this->subcategoryId,
            'amount' => $this->amount,
            'description' => $this->description,
            'date' => $this->date,
            'tags' => $this->tags,
            'isRecurring' => $this->isRecurring,
            'recurringFrequency' => $this->recurringFrequency,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt
        ];
    }

    public function addTag(string $tag): void
    {
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
            $this->updatedAt = date('c');
        }
    }

    public function removeTag(string $tag): bool
    {
        $index = array_search($tag, $this->tags);
        if ($index !== false) {
            unset($this->tags[$index]);
            $this->tags = array_values($this->tags);
            $this->updatedAt = date('c');
            return true;
        }
        return false;
    }

    public function updateAmount(float $amount): void
    {
        $this->amount = $amount;
        $this->updatedAt = date('c');
    }

    public function updateDescription(string $description): void
    {
        $this->description = $description;
        $this->updatedAt = date('c');
    }
}
