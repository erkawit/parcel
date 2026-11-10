# 🔐 New Roles Implementation (Department Head & Director)

## ✅ Overview

Implemented two new user roles to enhance access control:

1. **Department Head (`department_head`)**:
   - Can manage assets _only_ within their own department.
   - Can see assets assigned to users in their department OR assets located in their department.
   - Restricted from editing/deleting assets outside their scope.

2. **Director (`director`)**:
   - Has privileges equivalent to **Supply Officer** (`supply_officer`).
   - Can view and manage _all_ assets.

---

## 🛠️ Database Changes

- Updated `users` table `role` column to include `department_head` and `director`.
- Migration file: `database/migrations/add_new_user_roles.sql`

---

## 💻 Code Changes

### 1. `includes/auth.php`

- Updated `getRoleLabel()` to show Thai labels for new roles.
- Added `canAccessAsset($assetId, $userId)`:
  - Checks if a user has permission to access/manage a specific asset.
  - Implements the core logic for Department Head scope.
- Added `getAssetFilterByRole()`:
  - Returns SQL WHERE clause and parameters for filtering asset lists based on role.
- Added `hasManagementPrivileges()`:
  - Returns true for admin, supply_officer, director, department_head.

### 2. `users.php` (User Management)

- Updated "Add User" and "Edit User" forms to include the new roles in the dropdown.
- Added specific badge colors for new roles:
  - Department Head: Purple (`bg-purple-100`)
  - Director: Indigo (`bg-indigo-100`)

### 3. `api/assets.php` (Assets API)

- Implemented `getAssetFilterByRole()` in `list` action.
- Added access checks using `canAccessAsset()` in `get`, `update`, and `delete` actions.
- Department Heads attempting to manage out-of-scope assets will receive an error message.

### 4. `assets.php` (Asset Dashboard)

- Replaced manual `$isManager` checks with `hasManagementPrivileges()` and `canAccessAsset()`.
- Assets list is now automatically filtered based on the user's role.
- "Edit" and "Delete" buttons are hidden for assets the user cannot manage.

---

## 🧪 Testing Scenarios

| Role          | Scenario              | Expected Result                             |
| ------------- | --------------------- | ------------------------------------------- |
| **Director**  | View All Assets       | See all assets in the system.               |
| **Director**  | Edit Any Asset        | Success.                                    |
| **Dept Head** | View Own Dept Asset   | Visible.                                    |
| **Dept Head** | View Other Dept Asset | Not visible in list. Access denied via API. |
| **Dept Head** | Edit Own Dept Asset   | Success.                                    |
| **Dept Head** | Edit Other Dept Asset | access denied (if tried via API directly).  |

---

## 📝 Notes

- Ensure that users with `department_head` role have their `department` field correctly set in their profile, as this is used for scoping access.
