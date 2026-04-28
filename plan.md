# Next Move: Align Employee Access and Evaluation Flow with Client Requirements

## Summary
Refactor the current access and portal flow so it fully matches the client’s clarified intent:

- general employees must have their **own Employee account**
- employees must be able to **view their employment information**
- employees must be able to **encode their own self-rating** for the 360-degree evaluation process
- `HR Staff` must remain a **separate HR access level**, distinct from general employees
- `HR Staff`, `HR Supervisor`, and `HR Manager` remain the only roles authorized to **edit employee information**

This work should correct the current shared-account behavior in the Employee Portal and establish a clean separation between Employee self-service and HR administrative functions.

## Key Changes

### 1. Enforce Separate Employee Accounts
- Stop treating the Employee Portal as a fallback portal for any non-Admin account linked to an employee record.
- Change Employee Portal access so it is intended for **Employee accounts only**.
- Preserve separate login contexts:
  - Employee account → Employee Portal
  - HR Staff / HR Supervisor / HR Manager → HR Portal
- Remove or phase out shared-access behavior that lets HR-role accounts use Employee pages as if they were employee accounts.

### 2. Add Official Employee Self-Service View
- Add a dedicated `My Employment` page in the Employee Portal.
- This page becomes the official employee-facing view of employment information.
- The page should be **read-only** in v1 and include:
  - employee ID
  - full name
  - job title / position
  - department
  - branch
  - hire date
  - employment status
  - employment type
  - contact information
  - government ID summary and other profile summary fields already safe for employee viewing
- Keep HR-side employee editing separate.

### 3. Keep PDS and Employment View as Separate Flows
- `My Employment` is for viewing official information.
- `My PDS` remains for viewing PDS submission data and status.
- `PDS Wizard` remains for completing/updating the Personal Data Sheet workflow.
- Do not overload `My PDS` as the main employment-information page.

### 4. Introduce Employee Self-Rating Preparation
- Prepare the system so Employee accounts can later participate in the 360-degree evaluation process through self-rating.
- The next employee evaluation flow should begin with:
  - Employee account logs in
  - employee accesses self-rating screen
  - employee encodes and submits their own rating
- Supervisor / Manager / HR review and approval workflow remains separate from the employee self-service step.
- This phase should define the correct place in the Employee Portal navigation for self-rating, even if the first implementation focuses on access cleanup and employment-info view first.

### 5. Preserve HR Administrative Separation
- Keep `HR Staff` as a distinct HR-only access level, not as a general employee role.
- `HR Staff`, `HR Supervisor`, and `HR Manager` continue to hold authority for employee information maintenance and HR workflows.
- Employees remain restricted to self-service functions such as:
  - viewing employment information
  - viewing their own PDS-related status/data
  - completing self-rating when that module is implemented

## Current System Changes Required
- Update Employee Portal access checks so they no longer accept any linked non-Admin account by default.
- Update Employee Portal login rules so they align with separate Employee accounts as the expected access model.
- Review sidebar/menu logic that currently makes HR accounts behave as `Employee` inside `/employee/*`.
- Review notification/account helper behavior where employee-portal targeting still falls back to non-Employee linked accounts.
- Keep current notification isolation rules, but make them consistent with the stricter separate-account model.

## Interfaces / Behavior Changes
- **Employee Portal login**
  - should target Employee accounts as the intended login path
- **Employee Portal navigation**
  - add `My Employment`
  - reserve space for future `Self-Rating` or equivalent evaluation entry
- **Permissions**
  - Employee account: self-service only
  - HR Staff / HR Supervisor / HR Manager: HR workflow and employee record maintenance
- **Account model**
  - separate Employee and HR accounts remain the official rule

## Test Plan

### Access and Separation
- Employee account can log into Employee Portal
- HR Staff account cannot be treated as the Employee Portal account by default
- separate Employee and HR accounts for the same person do not share portal behavior, permissions, or notifications

### Employment Information View
- Employee can open `My Employment`
- displayed employment fields match the official employee record
- page is read-only
- missing optional fields render safely

### Existing Flow Safety
- `My PDS` still works
- `PDS Wizard` still works
- HR-side employee edit pages still work
- Portal Account management still works for both Employee accounts and HR accounts

### Future Self-Rating Readiness
- Employee Portal structure clearly supports adding self-rating next
- evaluation flow remains compatible with separate Employee and HR roles

## Assumptions / Defaults
- The client’s direction overrides the earlier shared-account Employee Portal behavior.
- Separate Employee and HR accounts are now the intended system design.
- `My Employment` is read-only in the first implementation.
- Employee self-rating is required by the system direction and should be treated as the next functional phase after access-model cleanup and employment-information viewing.
- HR Staff remains an HR administrative role and must not be merged into general Employee access.
