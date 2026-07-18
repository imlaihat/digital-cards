# Workflow

## Table of Contents
- [Description](#description)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Sub-modules](#sub-modules)
- [API](#api)
- [Testing](#testing)
- [Migration](#migration)
- [Troubleshooting](#troubleshooting)
- [Maintainers](#maintainers)

## Description

The Workflow module allows you to create customized workflow states and transitions for any entity type in Drupal. It provides a powerful framework for managing content lifecycle, approval processes, and business workflows.

Key features:
- Create unlimited workflow types with custom states and transitions
- Apply workflows to any entity type (nodes, users, custom entities)
- Role-based transition permissions
- Scheduled transitions
- Workflow history tracking
- Views integration for reporting
- Rules integration support
- Migration tools for upgrading from Drupal 7

## Requirements

This module requires the following:

**Drupal Core:**
- Drupal 8.8, 9.x, 10.x, or 11.x

**Core Dependencies:**
- Field module
- Options module
- User module

**Recommended:**
- Views module (for workflow history and reporting)
- Rules module (for automated workflow actions)

## Installation

### Via Composer (Recommended)
```bash
composer require drupal/workflow
```

### Manual Installation
1. Download the latest version from https://www.drupal.org/project/workflow
2. Extract the files to `/modules/contrib/workflow`
3. Enable the module:
```bash
drush en workflow
```

Or enable through the admin interface at Administration Â» Extend.

## Configuration

### Basic Setup

1. **Enable the module**
   Navigate to Administration Â» Extend and enable the Workflow module.

2. **Create a Workflow**
   - Go to Administration Â» Structure Â» Workflow types
   - Click "Add workflow type"
   - Enter a label and machine name
   - Configure workflow options

3. **Add Workflow States**
   - In your workflow, go to the States tab
   - Add states like "Draft", "Review", "Published"
   - Configure state properties and permissions

4. **Configure Transitions**
   - Go to the Transitions tab
   - Define allowed transitions between states
   - Set role permissions for each transition

5. **Apply to Content Types**
   - Go to Administration Â» Structure Â» Content types
   - Edit a content type
   - Add a Workflow field
   - Configure the field to use your workflow

### Advanced Configuration

#### Workflow Options
- **Comment logging**: Enable transition comments
- **Options tab**: Show/hide workflow options on entity forms
- **History tab**: Display workflow history on entities

#### Field Configuration
- **Widget settings**: Configure the workflow transition element
- **Default state**: Set initial state for new entities
- **Required comment**: Force users to add comments on transitions

## Usage

### Basic Workflow Operations

**Content Creators:**
1. Create content - it starts in the initial state
2. Make state transitions using the workflow widget
3. Add comments when transitioning (if required)

**Editors/Reviewers:**
1. Review content in "Review" state
2. Approve or reject with appropriate transitions
3. View workflow history for audit trails

### Scheduled Transitions

Enable scheduled transitions to automatically change states:
1. Configure the "Schedule" permission for roles
2. Users can schedule future state changes
3. Run cron regularly to process scheduled transitions

### Workflow History

All transitions are logged with:
- Timestamp of transition
- User who performed transition
- From/to states
- Comments (if provided)

Access history via the "Workflow" tab on entities.

## Sub-modules

### Workflow UI
Provides administrative interfaces for managing workflows.
- Enable: `drush en workflow_ui`
- Access: Administration Â» Structure Â» Workflow types

### Workflow Access
Advanced access control for workflow transitions.
- Enable: `drush en workflow_access`
- Configure per-state access permissions

### Workflow Operations
Bulk operations for workflow states.
- Enable: `drush en workflow_operations`
- Perform bulk state changes

### Workflow Cleanup
Tools for cleaning up workflow data.
- Enable: `drush en workflow_cleanup`
- Remove orphaned workflow transitions

### Workflow Devel
Development tools and debugging.
- Enable: `drush en workflow_devel`
- Provides hook debugging and development aids

## API

### Hooks

The Workflow module provides several hooks for custom development.
See file workflow.api.php for detailed information.

### Programmatic Transitions

```php
// Load the entity
$entity = Node::load(123);

// Create a transition
$transition = WorkflowTransition::create([
  'entity_type' => 'node',
  'entity_id' => $entity->id(),
  'field_name' => 'field_workflow',
  'to_sid' => 'published',
  'comment' => 'Published via API',
]);

// Execute the transition
$transition->executeTransition();
```

## Testing

### Running Unit Tests

The Workflow module includes comprehensive unit tests. You need to run them on a Drupal instance
with phpunit installed.

Change www below to web depending on what you're webroot is:

```bash
# Run all workflow tests.
cd www && ../vendor/bin/phpunit --configuration core/phpunit.xml.dist modules/contrib/workflow/tests/ --testdox

# Run all workflow tests using ddev.
ddev exec "cd www && ../vendor/bin/phpunit --configuration core/phpunit.xml.dist modules/contrib/workflow/tests/ --testdox"

# Run specific test classes.
cd www && ../vendor/bin/phpunit --configuration core/phpunit.xml.dist modules/contrib/workflow/tests/src/Unit/WorkflowTest.php --testdox

# Run tests with coverage (requires xdebug).
cd www && ../vendor/bin/phpunit --configuration core/phpunit.xml.dist --coverage-html /tmp/coverage modules/contrib/workflow/tests/
```

### Available Test Classes

- `WorkflowTest`: Tests core workflow functionality
- `WorkflowStateTest`: Tests workflow state operations
- `WorkflowTransitionTest`: Tests transition logic
- `WorkflowManagerTest`: Tests the workflow manager service
- `WorkflowPermissionsTest`: Tests permission handling
- `WorkflowHistoryAccessTest`: Tests history access controls

## Migration

### Migrating from Drupal 7

The module includes migration plugins for upgrading from Drupal 7:

1. **Migrate Workflows**: `d7_workflow`
2. **Migrate States**: `d7_workflow_state`
3. **Migrate Transitions**: `d7_workflow_transition`
4. **Migrate Config Transitions**: `d7_workflow_config_transition`
5. **Migrate Scheduled Transitions**: `d7_workflow_scheduled_transition`

Run migrations:
```bash
drush migrate:import d7_workflow_state
drush migrate:import d7_workflow
drush migrate:import d7_workflow_config_transition
drush migrate:import d7_workflow_transition
```

### Custom Entity Migration

For entities other than nodes, customize the migration files:

```yaml
# In your custom migration file
process:
  entity_type:
    -
      plugin: skip_on_value
      source: entity_type
      method: row
      not_equals: true
      value: your_entity_type
  entity_id:
    -
      plugin: migration_lookup
      migration: your_entity_migration
      source: entity_id_field
migration_dependencies:
  required:
    - your_entity_migration
    - d7_workflow_state
```

## Troubleshooting

### Common Issues

**Problem**: Transitions not showing up
- **Solution**: Check role permissions for transitions
- **Check**: Entity edit permissions and workflow field access

**Problem**: Scheduled transitions not executing
- **Solution**: Ensure cron is running regularly
- **Check**: User permissions for scheduled transitions

**Problem**: Workflow history not displaying
- **Solution**: Enable the workflow history view mode
- **Check**: Field display settings for the workflow field

**Problem**: Migration fails
- **Solution**: Check migration dependencies are met
- **Check**: Verify source database connection and data integrity

### Debug Mode

Enable workflow_devel for debugging:
```bash
drush en workflow_devel
```

This will log all workflow hooks and operations to help identify issues.

### Performance

For sites with many workflow transitions:
1. Configure appropriate indexes on workflow_transition table
2. Archive old transitions if history grows too large
3. Consider using workflow_cleanup for maintenance

## Maintainers

Current maintainers:
- [johnv](https://www.drupal.org/u/johnv)

## Additional Resources

- **Project page**: https://www.drupal.org/project/workflow
- **Documentation**: https://www.drupal.org/docs/contributed-modules/workflow
- **Issue queue**: https://www.drupal.org/project/issues/workflow
- **Security issues**: Report via private security issue queue

## License

This project is licensed under the GNU General Public License version 2.0.
See LICENSE.txt for the full license text.
