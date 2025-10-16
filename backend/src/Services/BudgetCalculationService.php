<?php

namespace Prism\Backend\Services;

use Prism\Backend\Models\Budget;
use Prism\Backend\Models\Expense;
use Prism\Backend\Models\SavingsGoal;

class BudgetCalculationService
{
    public function calculateBudgetAnalysis(Budget $budget, array $expenses = []): array
    {
        $totalIncome = $budget->monthlyIncome;
        $totalExpenses = $budget->monthlyExpenses;
        $netIncome = $budget->getNetIncome();
        $savingsRate = $budget->getSavingsRate();

        // Calculate actual expenses by category
        $categoryBreakdown = $this->calculateCategoryBreakdown($budget, $expenses);
        
        // Calculate monthly trends (last 6 months)
        $monthlyTrend = $this->calculateMonthlyTrend($expenses);
        
        // Generate recommendations
        $recommendations = $this->generateRecommendations($budget, $expenses);
        
        // Generate alerts
        $alerts = $this->generateAlerts($budget, $expenses);

        return [
            'totalIncome' => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'netIncome' => $netIncome,
            'savingsRate' => $savingsRate,
            'categoryBreakdown' => $categoryBreakdown,
            'monthlyTrend' => $monthlyTrend,
            'recommendations' => $recommendations,
            'alerts' => $alerts
        ];
    }

    public function calculateSavingsProjection(SavingsGoal $goal, array $contributions = []): array
    {
        $currentAmount = $goal->currentAmount;
        $targetAmount = $goal->targetAmount;
        $targetDate = new \DateTime($goal->targetDate);
        $currentDate = new \DateTime();
        
        $monthsRemaining = $this->calculateMonthsRemaining($currentDate, $targetDate);
        $requiredMonthlyContribution = $monthsRemaining > 0 ? 
            ($targetAmount - $currentAmount) / $monthsRemaining : 0;
        
        $isOnTrack = $goal->monthlyContribution >= $requiredMonthlyContribution;
        
        // Calculate projected amount based on current contributions
        $projectedAmount = $currentAmount + ($goal->monthlyContribution * $monthsRemaining);
        $projectedDate = $this->calculateProjectedDate($goal, $contributions);

        return [
            'goalId' => $goal->id,
            'goalName' => $goal->name,
            'currentAmount' => $currentAmount,
            'projectedAmount' => min($projectedAmount, $targetAmount),
            'projectedDate' => $projectedDate,
            'isOnTrack' => $isOnTrack,
            'requiredMonthlyContribution' => $requiredMonthlyContribution,
            'confidence' => $this->calculateConfidence($goal, $contributions)
        ];
    }

    public function calculateExpenseProjection(array $expenses, int $months = 12): array
    {
        $projections = [];
        $categoryTotals = $this->groupExpensesByCategory($expenses);
        
        foreach ($categoryTotals as $categoryId => $categoryData) {
            $trend = $this->calculateTrend($categoryData['amounts']);
            $projectedMonth = $this->projectNextMonth($categoryData['amounts'], $trend);
            $projectedYear = $projectedMonth * 12;
            
            $projections[] = [
                'categoryId' => $categoryId,
                'categoryName' => $categoryData['name'],
                'currentMonth' => end($categoryData['amounts']) ?: 0,
                'projectedMonth' => $projectedMonth,
                'projectedYear' => $projectedYear,
                'trend' => $trend['direction'],
                'confidence' => $trend['confidence']
            ];
        }
        
        return $projections;
    }

    private function calculateCategoryBreakdown(Budget $budget, array $expenses): array
    {
        $breakdown = [];
        $actualAmounts = $this->groupExpensesByCategory($expenses);
        
        foreach ($budget->categories as $category) {
            $actualAmount = $actualAmounts[$category['id']]['total'] ?? 0;
            $budgetedAmount = $category['amount'];
            $variance = $actualAmount - $budgetedAmount;
            $variancePercentage = $budgetedAmount > 0 ? 
                ($variance / $budgetedAmount) * 100 : 0;
            
            $breakdown[] = [
                'categoryId' => $category['id'],
                'categoryName' => $category['name'],
                'budgetedAmount' => $budgetedAmount,
                'actualAmount' => $actualAmount,
                'variance' => $variance,
                'variancePercentage' => $variancePercentage,
                'color' => $category['color']
            ];
        }
        
        return $breakdown;
    }

    private function calculateMonthlyTrend(array $expenses): array
    {
        $monthlyData = [];
        $currentDate = new \DateTime();
        
        for ($i = 5; $i >= 0; $i--) {
            $month = clone $currentDate;
            $month->modify("-{$i} months");
            $monthKey = $month->format('Y-m');
            
            $monthlyExpenses = array_filter($expenses, function($expense) use ($monthKey) {
                return date('Y-m', strtotime($expense->date)) === $monthKey;
            });
            
            $totalExpenses = array_sum(array_column($monthlyExpenses, 'amount'));
            
            $monthlyData[] = [
                'month' => $month->format('M Y'),
                'income' => 0, // Would need income data
                'expenses' => $totalExpenses,
                'savings' => 0 // Would need income data
            ];
        }
        
        return $monthlyData;
    }

    private function generateRecommendations(Budget $budget, array $expenses): array
    {
        $recommendations = [];
        
        // Check savings rate
        if ($budget->getSavingsRate() < 20) {
            $recommendations[] = "Consider increasing your savings rate to at least 20% for better financial security.";
        }
        
        // Check for overspending categories
        $categoryBreakdown = $this->calculateCategoryBreakdown($budget, $expenses);
        foreach ($categoryBreakdown as $category) {
            if ($category['variancePercentage'] > 20) {
                $recommendations[] = "You're overspending in {$category['categoryName']} by {$category['variancePercentage']}%. Consider reducing expenses in this category.";
            }
        }
        
        // Check for unused budget
        foreach ($categoryBreakdown as $category) {
            if ($category['variancePercentage'] < -20) {
                $recommendations[] = "You have unused budget in {$category['categoryName']}. Consider reallocating to other categories or increasing savings.";
            }
        }
        
        return $recommendations;
    }

    private function generateAlerts(Budget $budget, array $expenses): array
    {
        $alerts = [];
        $categoryBreakdown = $this->calculateCategoryBreakdown($budget, $expenses);
        
        foreach ($categoryBreakdown as $category) {
            if ($category['variancePercentage'] > 50) {
                $alerts[] = [
                    'id' => uniqid(),
                    'type' => 'over_budget',
                    'message' => "You've exceeded your {$category['categoryName']} budget by {$category['variancePercentage']}%",
                    'severity' => 'high',
                    'categoryId' => $category['categoryId'],
                    'createdAt' => date('c')
                ];
            }
        }
        
        if ($budget->getSavingsRate() < 10) {
            $alerts[] = [
                'id' => uniqid(),
                'type' => 'low_savings',
                'message' => 'Your savings rate is very low. Consider increasing your monthly savings.',
                'severity' => 'medium',
                'createdAt' => date('c')
            ];
        }
        
        return $alerts;
    }

    private function groupExpensesByCategory(array $expenses): array
    {
        $grouped = [];
        
        foreach ($expenses as $expense) {
            $categoryId = $expense->categoryId;
            if (!isset($grouped[$categoryId])) {
                $grouped[$categoryId] = [
                    'name' => $categoryId, // Would need to get from budget
                    'total' => 0,
                    'amounts' => []
                ];
            }
            
            $grouped[$categoryId]['total'] += $expense->amount;
            $grouped[$categoryId]['amounts'][] = $expense->amount;
        }
        
        return $grouped;
    }

    private function calculateTrend(array $amounts): array
    {
        if (count($amounts) < 2) {
            return ['direction' => 'stable', 'confidence' => 0.5];
        }
        
        $recent = array_slice($amounts, -3);
        $older = array_slice($amounts, -6, 3);
        
        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = count($older) > 0 ? array_sum($older) / count($older) : $recentAvg;
        
        $change = $recentAvg - $olderAvg;
        $changePercentage = $olderAvg > 0 ? ($change / $olderAvg) * 100 : 0;
        
        if ($changePercentage > 10) {
            return ['direction' => 'increasing', 'confidence' => min(1.0, abs($changePercentage) / 50)];
        } elseif ($changePercentage < -10) {
            return ['direction' => 'decreasing', 'confidence' => min(1.0, abs($changePercentage) / 50)];
        } else {
            return ['direction' => 'stable', 'confidence' => 0.8];
        }
    }

    private function projectNextMonth(array $amounts, array $trend): float
    {
        if (empty($amounts)) {
            return 0;
        }
        
        $recentAvg = array_sum(array_slice($amounts, -3)) / min(3, count($amounts));
        
        switch ($trend['direction']) {
            case 'increasing':
                return $recentAvg * (1 + ($trend['confidence'] * 0.1));
            case 'decreasing':
                return $recentAvg * (1 - ($trend['confidence'] * 0.1));
            default:
                return $recentAvg;
        }
    }

    private function calculateMonthsRemaining(\DateTime $current, \DateTime $target): int
    {
        if ($target <= $current) {
            return 0;
        }
        
        $diff = $current->diff($target);
        return ($diff->y * 12) + $diff->m;
    }

    private function calculateProjectedDate(SavingsGoal $goal, array $contributions): string
    {
        $monthsNeeded = ceil($goal->getRemainingAmount() / $goal->monthlyContribution);
        $projectedDate = new \DateTime();
        $projectedDate->modify("+{$monthsNeeded} months");
        
        return $projectedDate->format('c');
    }

    private function calculateConfidence(SavingsGoal $goal, array $contributions): float
    {
        if (empty($contributions)) {
            return 0.5;
        }
        
        $recentContributions = array_slice($contributions, -6);
        $consistency = 1 - (array_sum(array_map(function($c) use ($goal) {
            return abs($c - $goal->monthlyContribution) / $goal->monthlyContribution;
        }, $recentContributions)) / count($recentContributions));
        
        return max(0.1, min(1.0, $consistency));
    }
}
