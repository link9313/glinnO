<?php

    use Illuminate\Database\Capsule\Manager as Capsule;
    use Illuminate\Database\Schema\Blueprint;
    use UserFrosting\Sprinkle\Account\Model\Permission;
    use UserFrosting\Sprinkle\Account\Model\User;
    use UserFrosting\Sprinkle\Account\Model\Role;
    use UserFrosting\Sprinkle\Event\Model\Event;

    if (!$schema->hasTable('events')) {
        $schema->create('events', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 50);
            $table->string('location', 50);
            $table->dateTime('start');
            $table->dateTime('end');
            $table->boolean('all_day')->default(0)->comment("Set to 1 if the event lasts all day.");
            $table->string('url', 50);
            $table->string('notes', 255);
            $table->boolean('flag_enabled')->default(0)->comment("Set to 1 if the event is currently enabled, 0 otherwise.");
            $table->integer('creator_id');
            $table->softDeletes();
            $table->timestamps();

            $table->engine = 'InnoDB';
            $table->collation = 'utf8_unicode_ci';
            $table->charset = 'utf8';
            $table->foreign('creator_id')->references('id')->on('users');
            $table->unique('name');
            $table->index('name');
        });
        echo "Created table 'events'..." . PHP_EOL;
    } else {
        echo "Table 'events' already exists.  Skipping..." . PHP_EOL;
    }

    /**
     * Roles replace "groups" in UF 0.3.x.  Users acquire permissions through roles.
     */
    if (!$schema->hasTable('roles') && !Role::where('name', '=', 'contributor')) {
        // Add new role
        $roles = [
            'contributor' => new Role([
                'slug' => 'contributor',
                'name' => 'Contributor',
                'description' => 'This role allows for the creation and deletion of events.'
        ])];

        foreach ($roles as $slug => $role) {
            $role->save();
        }
        echo "Extended table 'roles'..." . PHP_EOL;
    } else {
        echo "Table 'roles' doesn't exist.  Skipping..." . PHP_EOL;
    }

    /**
     * Permissions now replace the 'authorize_group' and 'authorize_user' tables.
     * Also, they now map many-to-many to roles.
     */
    if (!$schema->hasTable('permissions')) {
        $defaultRoleIds = [
            'user' => Role::where('slug', 'user')->first()->id,
            'group-admin' => Role::where('slug', 'group-admin')->first()->id,
            'site-admin' => Role::where('slug', 'site-admin')->first()->id,
            'contributor' => Role::where('slug', 'contributor')->first()->id
        ];

        // Add default permissions
        $permissions = [
            'create_group' => new Permission([
                'slug' => 'create_group',
                'name' => 'Create group',
                'conditions' => 'always()',
                'description' => 'Create a new group.'
            ]),
            'create_user' => new Permission([
                'slug' => 'create_user',
                'name' => 'Create user',
                'conditions' => 'always()',
                'description' => 'Create a new user in your own group and assign default roles.'
            ]),
            'create_user_field' => new Permission([
                'slug' => 'create_user_field',
                'name' => 'Set new user group',
                'conditions' => "subset(fields,['group'])",
                'description' => 'Set the group when creating a new user.'
            ]),
            'delete_group' => new Permission([
                'slug' => 'delete_group',
                'name' => 'Delete group',
                'conditions' => "always()",
                'description' => 'Delete a group.'
            ]),
            'delete_user' => new Permission([
                'slug' => 'delete_user',
                'name' => 'Delete user',
                'conditions' => "!has_role(user.id,{$defaultRoleIds['site-admin']}) && !is_master(user.id)",
                'description' => 'Delete users who are not Site Administrators.'
            ]),
            'update_account_settings' => new Permission([
                'slug' => 'update_account_settings',
                'name' => 'Edit user',
                'conditions' => 'always()',
                'description' => 'Edit your own account settings.'
            ]),
            'update_group_field' => new Permission([
                'slug' => 'update_group_field',
                'name' => 'Edit group',
                'conditions' => 'always()',
                'description' => 'Edit basic properties of any group.'
            ]),
            'update_user_field' => new Permission([
                'slug' => 'update_user_field',
                'name' => 'Edit user',
                'conditions' => "!has_role(user.id,{$defaultRoleIds['site-admin']}) && subset(fields,['name','email','locale','group','flag_enabled','flag_verified','password'])",
                'description' => 'Edit users who are not Site Administrators.'
            ]),
            'update_user_field_group' => new Permission([
                'slug' => 'update_user_field',
                'name' => 'Edit group user',
                'conditions' => "equals_num(self.group_id,user.group_id) && !is_master(user.id) && !has_role(user.id,{$defaultRoleIds['site-admin']}) && (!has_role(user.id,{$defaultRoleIds['group-admin']}) || equals_num(self.id,user.id)) && subset(fields,['name','email','locale','flag_enabled','flag_verified','password'])",
                'description' => 'Edit users in your own group who are not Site or Group Administrators, except yourself.'
            ]),
            'uri_account_settings' => new Permission([
                'slug' => 'uri_account_settings',
                'name' => 'Account settings page',
                'conditions' => 'always()',
                'description' => 'View the account settings page.'
            ]),
            'uri_activities' => new Permission([
                'slug' => 'uri_activities',
                'name' => 'Activity monitor',
                'conditions' => 'always()',
                'description' => 'View a list of all activities for all users.'
            ]),
            'uri_dashboard' => new Permission([
                'slug' => 'uri_dashboard',
                'name' => 'Admin dashboard',
                'conditions' => 'always()',
                'description' => 'View the administrative dashboard.'
            ]),
            'uri_group' => new Permission([
                'slug' => 'uri_group',
                'name' => 'View group',
                'conditions' => 'always()',
                'description' => 'View the group page of any group.'
            ]),
            'uri_group_own' => new Permission([
                'slug' => 'uri_group',
                'name' => 'View own group',
                'conditions' => 'equals_num(self.group_id,group.id)',
                'description' => 'View the group page of your own group.'
            ]),
            'uri_groups' => new Permission([
                'slug' => 'uri_groups',
                'name' => 'Group management page',
                'conditions' => 'always()',
                'description' => 'View a page containing a list of groups.'
            ]),
            'uri_user' => new Permission([
                'slug' => 'uri_user',
                'name' => 'View user',
                'conditions' => 'always()',
                'description' => 'View the user page of any user.'
            ]),
            'uri_user_in_group' => new Permission([
                'slug' => 'uri_user',
                'name' => 'View user',
                'conditions' => "equals_num(self.group_id,user.group_id) && !is_master(user.id) && !has_role(user.id,{$defaultRoleIds['site-admin']}) && (!has_role(user.id,{$defaultRoleIds['group-admin']}) || equals_num(self.id,user.id))",
                'description' => 'View the user page of any user in your group, except the master user and Site and Group Administrators (except yourself).'
            ]),
            'uri_users' => new Permission([
                'slug' => 'uri_users',
                'name' => 'User management page',
                'conditions' => 'always()',
                'description' => 'View a page containing a table of users.'
            ]),
            'view_group_field' => new Permission([
                'slug' => 'view_group_field',
                'name' => 'View group',
                'conditions' => "in(property,['name','icon','slug','description','users'])",
                'description' => 'View certain properties of any group.'
            ]),
            'view_group_field_own' => new Permission([
                'slug' => 'view_group_field',
                'name' => 'View group',
                'conditions' => "equals_num(self.group_id,group.id) && in(property,['name','icon','slug','description','users'])",
                'description' => 'View certain properties of your own group.'
            ]),
            'view_user_field' => new Permission([
                'slug' => 'view_user_field',
                'name' => 'View user',
                'conditions' => "in(property,['user_name','name','email','locale','theme','roles','group','activities'])",
                'description' => 'View certain properties of any user.'
            ]),
            'view_user_field_group' => new Permission([
                'slug' => 'view_user_field',
                'name' => 'View user',
                'conditions' => "equals_num(self.group_id,user.group_id) && !is_master(user.id) && !has_role(user.id,{$defaultRoleIds['site-admin']}) && (!has_role(user.id,{$defaultRoleIds['group-admin']}) || equals_num(self.id,user.id)) && in(property,['user_name','name','email','locale','roles','group','activities'])",
                'description' => 'View certain properties of any user in your own group, except the master user and Site and Group Administrators (except yourself).'
            ]),
            'create_event' => new Permission([
                'slug' => 'create_event',
                'name' => 'Create event',
                'conditions' => 'always()',
                'description' => 'Create a new event.'
            ]),
            'delete_event' => new Permission([
                'slug' => 'delete_event',
                'name' => 'Delete event',
                'conditions' => "has_role(user.id,{$defaultRoleIds['contributor']}) && equals_num(self.id,event.creator_id)",
                'description' => 'Delete events owned by the user.'
            ]),
            'update_event_field' => new Permission([
                'slug' => 'update_event_field',
                'name' => 'Edit event',
                'conditions' => "has_role(user.id,{$defaultRoleIds['contributor']}) && equals_num(self.id,event.creator_id) && subset(fields,['name','location','date','all_day','start_time','end_time','url', 'notes', 'flag_enabled'])",
                'description' => 'Edit events owned by the user.'
            ]),
            'uri_event' => new Permission([
                'slug' => 'uri_event',
                'name' => 'View event',
                'conditions' => 'always()',
                'description' => 'View any event.'
            ]),
            'uri_events' => new Permission([
                'slug' => 'uri_events',
                'name' => 'Event management page',
                'conditions' => 'always()',
                'description' => 'View a page containing a table of events.'
            ]),
            'view_event_field' => new Permission([
                'slug' => 'view_event_field',
                'name' => 'View event',
                'conditions' => "in(property,[name','location','date','all_day','start_time','end_time','url','notes','creator_id'])",
                'description' => 'View certain properties of any event.'
            ])
        ];

        foreach ($permissions as $slug => $permission) {
            $permission->save();
        }

        // Add new mappings to default mappings to permissions
        $roleUser = Role::where('slug', 'user')->first();
        if ($roleUser) {
            $roleUser->permissions()->sync([
                $permissions['update_account_settings']->id,
                $permissions['uri_account_settings']->id,
                $permissions['uri_dashboard']->id,
                $permissions['uri_event']->id
            ]);
        }

        $roleContributor = Role::where('slug', 'contributor')->first();
        if ($roleContributor) {
            $roleContributor->permissions()->sync([
                $permissions['update_account_settings']->id,
                $permissions['uri_account_settings']->id,
                $permissions['uri_dashboard']->id,
                $permissions['uri_event']->id,
                $permissions['create_event']->id,
                $permissions['delete_event']->id,
                $permissions['update_event_field']->id,
                $permissions['view_event_field']->id
            ]);
        }

        $roleSiteAdmin = Role::where('slug', 'site-admin')->first();
        if ($roleSiteAdmin) {
            $roleSiteAdmin->permissions()->sync([
                $permissions['create_group']->id,
                $permissions['create_user']->id,
                $permissions['create_user_field']->id,
                $permissions['delete_group']->id,
                $permissions['delete_user']->id,
                $permissions['update_user_field']->id,
                $permissions['update_group_field']->id,
                $permissions['uri_activities']->id,
                $permissions['uri_group']->id,
                $permissions['uri_groups']->id,
                $permissions['uri_user']->id,
                $permissions['uri_users']->id,
                $permissions['view_group_field']->id,
                $permissions['view_user_field']->id,
                $permissions['uri_event']->id,
                $permissions['create_event']->id,
                $permissions['delete_event']->id,
                $permissions['update_event_field']->id,
                $permissions['view_event_field']->id,
                $permissions['uri_events']->id
            ]);
        }

        $roleGroupAdmin = Role::where('slug', 'group-admin')->first();
        if ($roleGroupAdmin) {
            $roleGroupAdmin->permissions()->sync([
                $permissions['create_user']->id,
                $permissions['update_user_field_group']->id,
                $permissions['uri_group_own']->id,
                $permissions['uri_user_in_group']->id,
                $permissions['view_group_field_own']->id,
                $permissions['view_user_field_group']->id
            ]);
        }

        echo "Extended table 'permissions'..." . PHP_EOL;
    } else {
        echo "Table 'permissions' doesn't exist.  Skipping..." . PHP_EOL;
    }
