-- ============================================================
-- LIMS Additional Seed Data
-- ============================================================

-- Additional Users
INSERT INTO users (username, email, password_hash, full_name, role_id, is_active) VALUES
('analyst1', 'analyst1@lims.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice Analyst', (SELECT id FROM roles WHERE name = 'Analyst'), TRUE),
('reviewer1', 'reviewer1@lims.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob Reviewer', (SELECT id FROM roles WHERE name = 'Reviewer'), TRUE),
('approver1', 'approver1@lims.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carol Approver', (SELECT id FROM roles WHERE name = 'Approver'), TRUE),
('customer1', 'customer1@lims.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dave Customer', (SELECT id FROM roles WHERE name = 'Customer'), TRUE)
ON CONFLICT (username) DO NOTHING;

-- Additional Customers
INSERT INTO customers (customer_code, customer_name, address, city, state, country, postal_code, contact_person, email, phone, is_active) VALUES
('CUST-002', 'PharmaCorp International', '456 Industry Blvd', 'Chicago', 'IL', 'USA', '60601', 'Jane Smith', 'jane@pharmacorp.com', '+1-555-0200', TRUE),
('CUST-003', 'BioGen Labs', '789 Science Park', 'San Francisco', 'CA', 'USA', '94105', 'Mike Johnson', 'mike@biogenlabs.com', '+1-555-0300', TRUE),
('CUST-004', 'ChemSolutions Ltd', '321 Chemical Ave', 'Houston', 'TX', 'USA', '77001', 'Sarah Lee', 'sarah@chemsolutions.com', '+1-555-0400', TRUE)
ON CONFLICT (customer_code) DO NOTHING;

-- Additional Products
INSERT INTO products (product_code, product_name, description, category, is_active) VALUES
('PROD-002', 'Purified Compound X', 'High-purity chemical compound', 'Pharmaceuticals', TRUE),
('PROD-003', 'Enzyme Solution Y', 'Concentrated enzyme solution', 'Biotechnology', TRUE),
('PROD-004', 'Polymer Resin Z', 'Industrial polymer resin', 'Polymers', TRUE)
ON CONFLICT (product_code) DO NOTHING;
