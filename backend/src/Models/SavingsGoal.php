<?php

namespace Prism\Backend\Models;

class SavingsGoal
{
    public string $id;
    public string $name;
    public ?string $description;
    public float $targetAmount;
    public float $currentAmount;
    public string $targetDate;
    public float $monthlyContribution;
    public string $category;
    public string $priority;
    public bool $isAchieved;
    public string $createdAt;
    public string $updatedAt;
    public ?string $userId;

    public function __construct(
        string $id,
        string $name,
        float $targetAmount,
        string $targetDate,
        float $monthlyContribution,
        string $category = 'other',
        string $priority = 'medium',
        ?string $description = null,
        ?string $userId = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->targetAmount = $targetAmount;
        $this->currentAmount = 0.0;
        $this->targetDate = $targetDate;
        $this->monthlyContribution = $monthlyContribution;
        $this->category = $category;
        $this->priority = $priority;
        $this->isAchieved = false;
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
            'targetAmount' => $this->targetAmount,
            'currentAmount' => $this->currentAmount,
            'targetDate' => $this->targetDate,
            'monthlyContribution' => $this->monthlyContribution,
            'category' => $this->category,
            'priority' => $this->priority,
            'isAchieved' => $this->isAchieved,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'userId' => $this->userId
        ];
    }

    public function addContribution(float $amount): void
    {
        $this->currentAmount += $amount;
        $this->updatedAt = date('c');
        
        if ($this->currentAmount >= $this->targetAmount) {
            $this->isAchieved = true;
        }
    }

    public function getProgressPercentage(): float
    {
        if ($this->targetAmount <= 0) {
            return 0;
        }
        return min(100, ($this->currentAmount / $this->targetAmount) * 100);
    }

    public function getRemainingAmount(): float
    {
        return max(0, $this->targetAmount - $this->currentAmount);
    }

    public function getMonthsRemaining(): int
    {
        $targetDate = new \DateTime($this->targetDate);
        $currentDate = new \DateTime();
        
        if ($targetDate <= $currentDate) {
            return 0;
        }
        
        $diff = $currentDate->diff($targetDate);
        return ($diff->y * 12) + $diff->m;
    }

    public function getRequiredMonthlyContribution(): float
    {
        $monthsRemaining = $this->getMonthsRemaining();
        if ($monthsRemaining <= 0) {
            return 0;
        }
        
        return $this->getRemainingAmount() / $monthsRemaining;
    }

    public function isOnTrack(): bool
    {
        return $this->getRequiredMonthlyContribution() <= $this->monthlyContribution;
    }

    public function updateTargetAmount(float $amount): void
    {
        $this->targetAmount = $amount;
        $this->updatedAt = date('c');
        
        if ($this->currentAmount >= $this->targetAmount) {
            $this->isAchieved = true;
        } else {
            $this->isAchieved = false;
        }
    }

    public function updateTargetDate(string $date): void
    {
        $this->targetDate = $date;
        $this->updatedAt = date('c');
    }

    public function updateMonthlyContribution(float $amount): void
    {
        $this->monthlyContribution = $amount;
        $this->updatedAt = date('c');
    }
}
