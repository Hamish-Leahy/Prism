<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Models\Budget;
use Prism\Backend\Models\Expense;
use Prism\Backend\Models\SavingsGoal;
use Prism\Backend\Models\ExpenseEstimate;
use Prism\Backend\Services\BudgetCalculationService;
use Prism\Backend\Services\ExpenseEstimationService;
use Prism\Backend\Services\DatabaseService;

class BudgetController
{
    private DatabaseService $database;
    private BudgetCalculationService $calculationService;
    private ExpenseEstimationService $estimationService;

    public function __construct(DatabaseService $database)
    {
        $this->database = $database;
        $this->calculationService = new BudgetCalculationService();
        $this->estimationService = new ExpenseEstimationService();
    }

    public function list(Request $request, Response $response): Response
    {
        try {
            $userId = $this->getUserId($request);
            $budgets = $this->database->query(
                "SELECT * FROM budgets WHERE user_id = ? ORDER BY created_at DESC",
                [$userId]
            );

            $budgetData = array_map(function($row) {
                return [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'monthlyIncome' => (float) $row['monthly_income'],
                    'monthlyExpenses' => (float) $row['monthly_expenses'],
                    'categories' => json_decode($row['categories'], true) ?: [],
                    'createdAt' => $row['created_at'],
                    'updatedAt' => $row['updated_at'],
                    'userId' => $row['user_id']
                ];
            }, $budgets);

            return $response->withJson([
                'success' => true,
                'data' => $budgetData
            ]);
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function create(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $userId = $this->getUserId($request);
            
            $budgetId = uniqid();
            $budget = new Budget(
                $budgetId,
                $data['name'],
                (float) $data['monthlyIncome'],
                (float) $data['monthlyExpenses'],
                $data['categories'] ?? [],
                $data['description'] ?? null,
                $userId
            );

            $this->database->query(
                "INSERT INTO budgets (id, user_id, name, description, monthly_income, monthly_expenses, categories, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $budgetId,
                    $userId,
                    $budget->name,
                    $budget->description,
                    $budget->monthlyIncome,
                    $budget->monthlyExpenses,
                    json_encode($budget->categories),
                    $budget->createdAt,
                    $budget->updatedAt
                ]
            );

            return $response->withJson([
                'success' => true,
                'data' => $budget->toArray()
            ], 201);
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        try {
            $budgetId = $args['id'];
            $userId = $this->getUserId($request);
            
            $budget = $this->database->query(
                "SELECT * FROM budgets WHERE id = ? AND user_id = ?",
                [$budgetId, $userId]
            );

            if (empty($budget)) {
                return $response->withJson([
                    'success' => false,
                    'error' => 'Budget not found'
                ], 404);
            }

            $budgetData = $budget[0];
            $budgetObj = new Budget(
                $budgetData['id'],
                $budgetData['name'],
                (float) $budgetData['monthly_income'],
                (float) $budgetData['monthly_expenses'],
                json_decode($budgetData['categories'], true) ?: [],
                $budgetData['description'],
                $budgetData['user_id']
            );

            return $response->withJson([
                'success' => true,
                'data' => $budgetObj->toArray()
            ]);
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $budgetId = $args['id'];
            $userId = $this->getUserId($request);
            $data = $request->getParsedBody();

            $this->database->query(
                "UPDATE budgets SET name = ?, description = ?, monthly_income = ?, monthly_expenses = ?, categories = ?, updated_at = ? WHERE id = ? AND user_id = ?",
                [
                    $data['name'],
                    $data['description'] ?? null,
                    (float) $data['monthlyIncome'],
                    (float) $data['monthlyExpenses'],
                    json_encode($data['categories'] ?? []),
                    date('c'),
                    $budgetId,
                    $userId
                ]
            );

            return $response->withJson([
                'success' => true,
                'data' => ['id' => $budgetId]
            ]);
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $budgetId = $args['id'];
            $userId = $this->getUserId($request);

            $this->database->query(
                "DELETE FROM budgets WHERE id = ? AND user_id = ?",
                [$budgetId, $userId]
            );

            return $response->withJson([
                'success' => true,
                'data' => ['id' => $budgetId]
            ]);
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAnalysis(Request $request, Response $response, array $args): Response
    {
        try {
            $budgetId = $args['id'];
            $userId = $this->getUserId($request);

            // Get budget
            $budgetData = $this->database->query(
                "SELECT * FROM budgets WHERE id = ? AND user_id = ?",
                [$budgetId, $userId]
            );

            if (empty($budgetData)) {
                return $response->withJson([
                    'success' => false,
                    'error' => 'Budget not found'
                ], 404);
            }

            $budget = new Budget(
                $budgetData[0]['id'],
                $budgetData[0]['name'],
                (float) $budgetData[0]['monthly_income'],
                (float) $budgetData[0]['monthly_expenses'],
                json_decode($budgetData[0]['categories'], true) ?: [],
                $budgetData[0]['description'],
                $budgetData[0]['user_id']
            );

            // Get expenses for this budget
            $expenses = $this->database->query(
                "SELECT * FROM expenses WHERE budget_id = ? ORDER BY date DESC",
                [$budgetId]
            );

            $expenseObjects = array_map(function($row) {
                return new Expense(
                    $row['id'],
                    $row['budget_id'],
                    $row['category_id'],
                    (float) $row['amount'],
                    $row['description'],
                    $row['date'],
                    json_decode($row['tags'], true) ?: [],
                    (bool) $row['is_recurring'],
                    $row['recurring_frequency'],
                    $row['subcategory_id']
                );
            }, $expenses);

            $analysis = $this->calculationService->calculateBudgetAnalysis($budget, $expenseObjects);

            return $response->withJson([
                'success' => true,
                'data' => $analysis
            ]);
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getExpenses(Request $request, Response $response, array $args): Response
    {
        try {
            $budgetId = $args['id'];
            $userId = $this->getUserId($request);

            // Verify budget belongs to user
            $budget = $this->database->query(
                "SELECT id FROM budgets WHERE id = ? AND user_id = ?",
                [$budgetId, $userId]
            );

            if (empty($budget)) {
                return $response->withJson([
                    'success' => false,
                    'error' => 'Budget not found'
                ], 404);
            }

            $expenses = $this->database->query(
                "SELECT * FROM expenses WHERE budget_id = ? ORDER BY date DESC",
                [$budgetId]
            );

            $expenseData = array_map(function($row) {
                return [
                    'id' => $row['id'],
                    'budgetId' => $row['budget_id'],
                    'categoryId' => $row['category_id'],
                    'subcategoryId' => $row['subcategory_id'],
                    'amount' => (float) $row['amount'],
                    'description' => $row['description'],
                    'date' => $row['date'],
                    'tags' => json_decode($row['tags'], true) ?: [],
                    'isRecurring' => (bool) $row['is_recurring'],
                    'recurringFrequency' => $row['recurring_frequency'],
                    'createdAt' => $row['created_at'],
                    'updatedAt' => $row['updated_at']
                ];
            }, $expenses);

            return $response->withJson([
                'success' => true,
                'data' => $expenseData
            ]);
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addExpense(Request $request, Response $response, array $args): Response
    {
        try {
            $budgetId = $args['id'];
            $userId = $this->getUserId($request);
            $data = $request->getParsedBody();

            // Verify budget belongs to user
            $budget = $this->database->query(
                "SELECT id FROM budgets WHERE id = ? AND user_id = ?",
                [$budgetId, $userId]
            );

            if (empty($budget)) {
                return $response->withJson([
                    'success' => false,
                    'error' => 'Budget not found'
                ], 404);
            }

            $expenseId = uniqid();
            $expense = new Expense(
                $expenseId,
                $budgetId,
                $data['categoryId'],
                (float) $data['amount'],
                $data['description'],
                $data['date'],
                $data['tags'] ?? [],
                $data['isRecurring'] ?? false,
                $data['recurringFrequency'] ?? null,
                $data['subcategoryId'] ?? null
            );

            $this->database->query(
                "INSERT INTO expenses (id, budget_id, category_id, subcategory_id, amount, description, date, tags, is_recurring, recurring_frequency, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $expenseId,
                    $budgetId,
                    $data['categoryId'],
                    $data['subcategoryId'] ?? null,
                    $expense->amount,
                    $expense->description,
                    $expense->date,
                    json_encode($expense->tags),
                    $expense->isRecurring ? 1 : 0,
                    $expense->recurringFrequency,
                    $expense->createdAt,
                    $expense->updatedAt
                ]
            );

            return $response->withJson([
                'success' => true,
                'data' => $expense->toArray()
            ], 201);
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSavingsGoals(Request $request, Response $response): Response
    {
        try {
            $userId = $this->getUserId($request);
            $goals = $this->database->query(
                "SELECT * FROM savings_goals WHERE user_id = ? ORDER BY created_at DESC",
                [$userId]
            );

            $goalData = array_map(function($row) {
                return [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'targetAmount' => (float) $row['target_amount'],
                    'currentAmount' => (float) $row['current_amount'],
                    'targetDate' => $row['target_date'],
                    'monthlyContribution' => (float) $row['monthly_contribution'],
                    'category' => $row['category'],
                    'priority' => $row['priority'],
                    'isAchieved' => (bool) $row['is_achieved'],
                    'createdAt' => $row['created_at'],
                    'updatedAt' => $row['updated_at'],
                    'userId' => $row['user_id']
                ];
            }, $goals);

            return $response->withJson([
                'success' => true,
                'data' => $goalData
            ]);
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createSavingsGoal(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $userId = $this->getUserId($request);
            
            $goalId = uniqid();
            $goal = new SavingsGoal(
                $goalId,
                $data['name'],
                (float) $data['targetAmount'],
                $data['targetDate'],
                (float) $data['monthlyContribution'],
                $data['category'] ?? 'other',
                $data['priority'] ?? 'medium',
                $data['description'] ?? null,
                $userId
            );

            $this->database->query(
                "INSERT INTO savings_goals (id, user_id, name, description, target_amount, current_amount, target_date, monthly_contribution, category, priority, is_achieved, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $goalId,
                    $userId,
                    $goal->name,
                    $goal->description,
                    $goal->targetAmount,
                    $goal->currentAmount,
                    $goal->targetDate,
                    $goal->monthlyContribution,
                    $goal->category,
                    $goal->priority,
                    $goal->isAchieved ? 1 : 0,
                    $goal->createdAt,
                    $goal->updatedAt
                ]
            );

            return $response->withJson([
                'success' => true,
                'data' => $goal->toArray()
            ], 201);
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function estimateExpense(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            $estimate = $this->estimationService->estimateExpense(
                $data['service'],
                $data['serviceName'],
                $data['parameters'] ?? []
            );

            return $response->withJson([
                'success' => true,
                'data' => $estimate->toArray()
            ]);
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getServiceSuggestions(Request $request, Response $response): Response
    {
        try {
            $query = $request->getQueryParams()['q'] ?? '';
            $suggestions = $this->estimationService->getServiceSuggestions($query);

            return $response->withJson([
                'success' => true,
                'data' => $suggestions
            ]);
        } catch (\Exception $e) {
            return $response->withJson([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getUserId(Request $request): string
    {
        // In a real implementation, this would extract user ID from JWT token
        // For now, we'll use a default user ID or extract from headers
        $headers = $request->getHeaders();
        $userId = $headers['X-User-ID'][0] ?? 'default-user';
        
        return $userId;
    }
}
