# Raquel HRIS

## Employee Evaluation (Raquel HRIS)

Employee evaluation in the Raquel HRIS is a structured performance management feature designed to assess employees based on defined Key Result Areas (KRAs) and behavioral competencies.

### Role of the Feature in the System

**1. Performance Measurement**

It evaluates employees using customizable templates such as annual or quarterly reviews with weighted criteria, for example 80% KRA and 20% Behavior, together with standardized scoring scales.

**2. Workflow Management**

It supports a multi-step review process:

- Draft
- Pending Supervisor Review
- Pending Manager Review
- Approved / Rejected / Returned

Each stage is assigned to specific roles such as employee, supervisor, manager, and HR.

**3. Data-Driven Decisions**

It generates total scores, subtotals, performance ratings, and development plans. These outputs support decisions on promotions, training needs, compensation adjustments, and overall talent management.

**4. Historical Tracking**

It stores evaluation history for each employee, enabling performance trend analysis and career progression tracking over time.

**5. Integration**

It is connected with employee records, user accounts, and development plans, ensuring that performance evaluations are fully integrated within the HRIS ecosystem.

### Summary

The employee evaluation feature provides a standardized and auditable system for assessing, documenting, and acting on employee performance, aligning individual contributions with organizational goals.

## User Access Levels

### 1. Employee

- Identified through a unique Employee ID
- Uses a user account for self-service access
- May view personal employment information
- May participate in self-rating if the self-evaluation module is enabled

### 2. Department / Unit Supervisor

- Reviews and evaluates employees within the assigned team or unit
- Participates in the approval and validation workflow for evaluations
- May assist in maintaining employee information as part of operational oversight

### 3. Department / Unit Manager

- Oversees supervisors and employee evaluations within the department or unit
- Holds approval authority in the evaluation workflow
- Shares responsibility for validating employee records and performance outcomes

## Admin Access Levels

These roles belong to the Human Resources Department and have elevated permissions in the HRIS.

### 1. HR Staff

- Maintains employee records and encodes HR data
- Authorized to edit employee information
- Operates separately from non-HR employees such as Marketing, IT, and Operations staff

### 2. HR Supervisor

- Oversees HR Staff activities and process execution
- Reviews HR data quality and supports validation of employee records

### 3. HR Manager

- Highest HR administrative authority
- Responsible for approvals, HR policy enforcement, and overall HRIS governance

## Key System Design Notes

- HR roles require separate access levels because they perform administrative functions
- Supervisors and managers share responsibility for validating employee information and evaluations
- Employees are limited to self-service functions unless broader participation, such as self-rating, is explicitly enabled in the system
- Notification visibility is account-based, not person-based
- An Employee account can view only notifications generated for that Employee account
- An HR account can view only notifications generated for that HR account
- There is no cross-visibility between two different accounts belonging to the same person

### Example

- `Sarah Connor (Employee account)` sees only Employee-account notifications
- `Sarah Connor (HR Staff account)` sees only HR-account notifications

## Implemented Features

- Separate access levels are established for `Employee`, `HR Staff`, `HR Supervisor`, `HR Manager`, and `Admin`
- HR roles remain distinct from regular employees to preserve administrative boundaries and process control
- Role-based access is enforced across the system for dashboards, records, approvals, and portal functions
- Employees can access a dedicated Employee Portal for self-service functions
- The Employee Portal login page was redesigned to align with the main HRIS login page for a consistent user experience
- Employees can access and manage their Personal Data Sheet (PDS) through the portal
- Runtime and autosave issues in the Employee PDS Wizard were fixed
- Username conflicts between HR accounts and Employee Portal access were resolved
- A single linked account can be used for Employee Portal access when the employee already has an HR-related system account
- A dedicated Employee Portal account management page was created for non-HR employees
- Admin can update portal usernames, reset passwords, activate or deactivate accounts, and delete Employee Portal accounts
- Duplicate employee entries in Portal Accounts were fixed
- The HR Manager `Edit Employee` Personal Data Sheet wizard was updated to match the design of the `Add New Employee` wizard
- The base schema and seed files were aligned with the current application behavior
- One-to-one constraints were added for employee details, contacts, emergency contacts, disclosures, government IDs, addresses, and family member types where the application expects a single active record
- Seed data was updated so a clean database import includes working sample accounts for Admin, HR Manager, HR Supervisor, HR Staff, and Employee roles
- The optional performance seed script now uses dynamic role-based user references instead of hard-coded IDs

# hris
