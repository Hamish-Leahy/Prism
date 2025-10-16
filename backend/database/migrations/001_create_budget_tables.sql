-- Create budgets table
CREATE TABLE IF NOT EXISTS budgets (
    id VARCHAR(255) PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    monthly_income DECIMAL(10,2) NOT NULL DEFAULT 0,
    monthly_expenses DECIMAL(10,2) NOT NULL DEFAULT 0,
    categories JSONB DEFAULT '[]',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create expenses table
CREATE TABLE IF NOT EXISTS expenses (
    id VARCHAR(255) PRIMARY KEY,
    budget_id VARCHAR(255) NOT NULL,
    category_id VARCHAR(255) NOT NULL,
    subcategory_id VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    date DATE NOT NULL,
    tags JSONB DEFAULT '[]',
    is_recurring BOOLEAN DEFAULT FALSE,
    recurring_frequency VARCHAR(20),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE
);

-- Create savings_goals table
CREATE TABLE IF NOT EXISTS savings_goals (
    id VARCHAR(255) PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    target_amount DECIMAL(10,2) NOT NULL,
    current_amount DECIMAL(10,2) DEFAULT 0,
    target_date DATE NOT NULL,
    monthly_contribution DECIMAL(10,2) NOT NULL,
    category VARCHAR(50) DEFAULT 'other',
    priority VARCHAR(20) DEFAULT 'medium',
    is_achieved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create expense_estimates table
CREATE TABLE IF NOT EXISTS expense_estimates (
    id VARCHAR(255) PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    service VARCHAR(50) NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    estimated_cost DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    location VARCHAR(255),
    date DATE,
    duration INTEGER,
    participants INTEGER,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create budget_templates table
CREATE TABLE IF NOT EXISTS budget_templates (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    categories JSONB NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_budgets_user_id ON budgets(user_id);
CREATE INDEX IF NOT EXISTS idx_budgets_created_at ON budgets(created_at);
CREATE INDEX IF NOT EXISTS idx_expenses_budget_id ON expenses(budget_id);
CREATE INDEX IF NOT EXISTS idx_expenses_category_id ON expenses(category_id);
CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses(date);
CREATE INDEX IF NOT EXISTS idx_savings_goals_user_id ON savings_goals(user_id);
CREATE INDEX IF NOT EXISTS idx_savings_goals_category ON savings_goals(category);
CREATE INDEX IF NOT EXISTS idx_savings_goals_target_date ON savings_goals(target_date);
CREATE INDEX IF NOT EXISTS idx_expense_estimates_user_id ON expense_estimates(user_id);
CREATE INDEX IF NOT EXISTS idx_expense_estimates_service ON expense_estimates(service);

-- Create updated_at trigger function
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for updated_at
CREATE TRIGGER update_budgets_updated_at BEFORE UPDATE ON budgets
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_expenses_updated_at BEFORE UPDATE ON expenses
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_savings_goals_updated_at BEFORE UPDATE ON savings_goals
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_expense_estimates_updated_at BEFORE UPDATE ON expense_estimates
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Insert default budget templates
INSERT INTO budget_templates (id, name, description, categories, is_default) VALUES
('template-50-30-20', '50/30/20 Rule', 'Classic budgeting rule: 50% needs, 30% wants, 20% savings', 
 '[
   {"name": "Needs", "type": "expense", "amount": 50, "color": "#ef4444", "icon": "home"},
   {"name": "Wants", "type": "expense", "amount": 30, "color": "#f59e0b", "icon": "shopping"},
   {"name": "Savings", "type": "expense", "amount": 20, "color": "#10b981", "icon": "piggy-bank"}
 ]', true),
('template-zero-based', 'Zero-Based Budget', 'Every dollar has a purpose', 
 '[
   {"name": "Housing", "type": "expense", "amount": 25, "color": "#3b82f6", "icon": "home"},
   {"name": "Food", "type": "expense", "amount": 15, "color": "#8b5cf6", "icon": "utensils"},
   {"name": "Transportation", "type": "expense", "amount": 10, "color": "#06b6d4", "icon": "car"},
   {"name": "Utilities", "type": "expense", "amount": 5, "color": "#f97316", "icon": "zap"},
   {"name": "Insurance", "type": "expense", "amount": 5, "color": "#84cc16", "icon": "shield"},
   {"name": "Entertainment", "type": "expense", "amount": 10, "color": "#ec4899", "icon": "music"},
   {"name": "Savings", "type": "expense", "amount": 20, "color": "#10b981", "icon": "piggy-bank"},
   {"name": "Emergency Fund", "type": "expense", "amount": 10, "color": "#6366f1", "icon": "alert-circle"}
 ]', false),
('template-minimalist', 'Minimalist Budget', 'Simple and focused', 
 '[
   {"name": "Essentials", "type": "expense", "amount": 60, "color": "#6b7280", "icon": "check-circle"},
   {"name": "Fun", "type": "expense", "amount": 20, "color": "#f59e0b", "icon": "smile"},
   {"name": "Future", "type": "expense", "amount": 20, "color": "#10b981", "icon": "trending-up"}
 ]', false);

-- Create RLS (Row Level Security) policies
ALTER TABLE budgets ENABLE ROW LEVEL SECURITY;
ALTER TABLE expenses ENABLE ROW LEVEL SECURITY;
ALTER TABLE savings_goals ENABLE ROW LEVEL SECURITY;
ALTER TABLE expense_estimates ENABLE ROW LEVEL SECURITY;

-- Create policies for budgets
CREATE POLICY "Users can view their own budgets" ON budgets
    FOR SELECT USING (auth.uid()::text = user_id);

CREATE POLICY "Users can insert their own budgets" ON budgets
    FOR INSERT WITH CHECK (auth.uid()::text = user_id);

CREATE POLICY "Users can update their own budgets" ON budgets
    FOR UPDATE USING (auth.uid()::text = user_id);

CREATE POLICY "Users can delete their own budgets" ON budgets
    FOR DELETE USING (auth.uid()::text = user_id);

-- Create policies for expenses
CREATE POLICY "Users can view expenses for their budgets" ON expenses
    FOR SELECT USING (
        budget_id IN (
            SELECT id FROM budgets WHERE user_id = auth.uid()::text
        )
    );

CREATE POLICY "Users can insert expenses for their budgets" ON expenses
    FOR INSERT WITH CHECK (
        budget_id IN (
            SELECT id FROM budgets WHERE user_id = auth.uid()::text
        )
    );

CREATE POLICY "Users can update expenses for their budgets" ON expenses
    FOR UPDATE USING (
        budget_id IN (
            SELECT id FROM budgets WHERE user_id = auth.uid()::text
        )
    );

CREATE POLICY "Users can delete expenses for their budgets" ON expenses
    FOR DELETE USING (
        budget_id IN (
            SELECT id FROM budgets WHERE user_id = auth.uid()::text
        )
    );

-- Create policies for savings_goals
CREATE POLICY "Users can view their own savings goals" ON savings_goals
    FOR SELECT USING (auth.uid()::text = user_id);

CREATE POLICY "Users can insert their own savings goals" ON savings_goals
    FOR INSERT WITH CHECK (auth.uid()::text = user_id);

CREATE POLICY "Users can update their own savings goals" ON savings_goals
    FOR UPDATE USING (auth.uid()::text = user_id);

CREATE POLICY "Users can delete their own savings goals" ON savings_goals
    FOR DELETE USING (auth.uid()::text = user_id);

-- Create policies for expense_estimates
CREATE POLICY "Users can view their own expense estimates" ON expense_estimates
    FOR SELECT USING (auth.uid()::text = user_id);

CREATE POLICY "Users can insert their own expense estimates" ON expense_estimates
    FOR INSERT WITH CHECK (auth.uid()::text = user_id);

CREATE POLICY "Users can update their own expense estimates" ON expense_estimates
    FOR UPDATE USING (auth.uid()::text = user_id);

CREATE POLICY "Users can delete their own expense estimates" ON expense_estimates
    FOR DELETE USING (auth.uid()::text = user_id);

-- Grant necessary permissions
GRANT USAGE ON SCHEMA public TO anon, authenticated;
GRANT ALL ON ALL TABLES IN SCHEMA public TO anon, authenticated;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO anon, authenticated;
GRANT ALL ON ALL FUNCTIONS IN SCHEMA public TO anon, authenticated;
