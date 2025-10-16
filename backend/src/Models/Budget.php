<?php

namespace Prism\Backend\Models;

class Budget
{
    public string $id;
    public string $name;
    public ?string $description;
    public float $monthlyIncome;
    public float $monthlyExpenses;
    public array $categories;
    public string $createdAt;
    public string $updatedAt;
    public ?string $userId;

    public function __construct(
        string $id,
        string $name,
        float $monthlyIncome,
        float $monthlyExpenses,
        array $categories = [],
        ?string $description = null,
        ?string $userId = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->monthlyIncome = $monthlyIncome;
        $this->monthlyExpenses = $monthlyExpenses;
        $this->categories = $categories;
        $this->createdAt = date('c');
        $this->updatedAt = date('c');
        $this->userId = $userId;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'monthlyIncome' => $this->monthlyIncome,
            'monthlyExpenses' => $this->monthlyExpenses,
            'categories' => $this->categories,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'userId' => $this->userId
        ];
    }

    public function getNetIncome(): float
    {
        return $this->monthlyIncome - $this->monthlyExpenses;
    }

    public function getSavingsRate(): float
    {
        if ($this->monthlyIncome <= 0) {
            return 0;
        }
        return ($this->getNetIncome() / $this->monthlyIncome) * 100;
    }

    public function addCategory(array $category): void
    {
        $this->categories[] = $category;
        $this->updatedAt = date('c');
    }

    public function updateCategory(string $categoryId, array $categoryData): bool
    {
        foreach ($this->categories as $index => $category) {
            if ($category['id'] === $categoryId) {
                $this->categories[$index] = array_merge($category, $categoryData);
                $this->updatedAt = date('c');
                return true;
            }
        }
        return false;
    }

    public function removeCategory(string $categoryId): bool
    {
        foreach ($this->categories as $index => $category) {
            if ($category['id'] === $categoryId) {
                unset($this->categories[$index]);
                $this->categories = array_values($this->categories);
                $this->updatedAt = date('c');
                return true;
            }
        }
        return false;
    }
}
