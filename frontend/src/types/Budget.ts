export interface Budget {
  id: string
  name: string
  description?: string
  monthlyIncome: number
  monthlyExpenses: number
  categories: BudgetCategory[]
  createdAt: string
  updatedAt: string
  userId?: string
}

export interface BudgetCategory {
  id: string
  name: string
  type: 'income' | 'expense'
  amount: number
  actualAmount?: number
  color: string
  icon: string
  subcategories?: BudgetSubcategory[]
}

export interface BudgetSubcategory {
  id: string
  name: string
  amount: number
  actualAmount?: number
  color: string
  icon: string
}

export interface Expense {
  id: string
  budgetId: string
  categoryId: string
  subcategoryId?: string
  amount: number
  description: string
  date: string
  tags: string[]
  isRecurring: boolean
  recurringFrequency?: 'weekly' | 'monthly' | 'yearly'
  createdAt: string
  updatedAt: string
}

export interface SavingsGoal {
  id: string
  name: string
  description?: string
  targetAmount: number
  currentAmount: number
  targetDate: string
  monthlyContribution: number
  category: 'travel' | 'emergency' | 'purchase' | 'experience' | 'other'
  priority: 'low' | 'medium' | 'high'
  isAchieved: boolean
  createdAt: string
  updatedAt: string
  userId?: string
}

export interface ExpenseEstimate {
  id: string
  service: 'airbnb' | 'hotel' | 'flight' | 'restaurant' | 'event' | 'transport' | 'other'
  serviceName: string
  estimatedCost: number
  currency: string
  location?: string
  date?: string
  duration?: number
  participants?: number
  metadata?: Record<string, any>
  createdAt: string
  updatedAt: string
}

export interface BudgetAnalysis {
  totalIncome: number
  totalExpenses: number
  netIncome: number
  savingsRate: number
  categoryBreakdown: CategoryBreakdown[]
  monthlyTrend: MonthlyTrend[]
  recommendations: string[]
  alerts: BudgetAlert[]
}

export interface CategoryBreakdown {
  categoryId: string
  categoryName: string
  budgetedAmount: number
  actualAmount: number
  variance: number
  variancePercentage: number
  color: string
}

export interface MonthlyTrend {
  month: string
  income: number
  expenses: number
  savings: number
}

export interface BudgetAlert {
  id: string
  type: 'over_budget' | 'low_savings' | 'unusual_spending' | 'goal_achieved' | 'goal_at_risk'
  message: string
  severity: 'low' | 'medium' | 'high'
  categoryId?: string
  createdAt: string
}

export interface BudgetTemplate {
  id: string
  name: string
  description: string
  categories: Omit<BudgetCategory, 'id'>[]
  isDefault: boolean
  createdAt: string
}

export interface BudgetComparison {
  budgetId: string
  budgetName: string
  period: string
  totalIncome: number
  totalExpenses: number
  netIncome: number
  savingsRate: number
  topCategories: CategoryBreakdown[]
}

export interface ExpenseProjection {
  categoryId: string
  categoryName: string
  currentMonth: number
  projectedMonth: number
  projectedYear: number
  trend: 'increasing' | 'decreasing' | 'stable'
  confidence: number
}

export interface SavingsProjection {
  goalId: string
  goalName: string
  currentAmount: number
  projectedAmount: number
  projectedDate: string
  isOnTrack: boolean
  requiredMonthlyContribution: number
  confidence: number
}
