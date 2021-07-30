<?php
/*
xminds.api.client
~~~~~~~~~~~~~~~~~

This module implements the requests for all API endpoints.
*/

require_once ("apirequest.php");
require_once ("exceptions.php");

class CrossingMindsApiClientImpl
{
    function __construct($api_kwargs)
    {
        $serializer = $api_kwargs['serializer'] ?? 'json';

        $this->_database = null;
        $this->_refresh_token = null;
        $this->auto_refresh_token = True;
        if (strtolower($serializer) == 'json')
        {
            $cls = "CrossingMindsApiJsonRequest";
            $this->b64_encode_bytes = True;
        }
        else
            throw NotImplementedError("unknown serializer {$serializer}");
        $this->api = new $cls($api_kwargs);
    }

    # === Account ===

    function create_individual_account($first_name, $last_name, $email, $password, $role='backend')
    {
        /*
        Create a new individual account

        :param str first_name:
        :param str last_name:
        :param str email:
        :param str password:
        :returns: ['id'=> str]
        */
        $path = 'accounts/individual/';
        $data = [
            'first_name'=> $first_name,
            'last_name'=> $last_name,
            'email'=> $email,
            'password'=> $password,
            'role'=> $role,
        ];
        return $this->api->post($path, $data);
    }

    function create_service_account($name, $password, $role='frontend')
    {
        /*
        Create a new service account

        :param str name:
        :param str password:
        :param str? role:
        :returns: ['id'=> str]
        */
        $path = 'accounts/service/';
        $data = [
            'name'=> $name,
            'password'=> $password,
            'role'=> $role,
        ];
        return $this->api->post($path, $data);
    }

    function resend_verification_code($email)
    {
        /*
        Resend the verification code to the account email

        :param str email:
        */
        $path = 'accounts/resend-verification-code/';
        return $this->api->put($path, ['email'=> $email]);
    }

    function verify_account($code, $email)
    {
        /*
        Verify the email of an account by entering the verification code

        :param str code:
        :param str email:
        */
        $path = 'accounts/verify/';
        $data = [
            'code'=> $code,
            'email'=> $email,
        ];
        return $this->api->get($path, $data);
    }

    function list_accounts()
    {
        /*
        Get all accounts on the current organization

        :returns: {
            'individual_accounts': [
                [
                    'first_name'=> str,
                    'last_name'=> str,
                    'email'=> str,
                    'role'=> str,
                    'verified'=> bool,
                ],
            ],
            'service_accounts': [
                [
                    'name'=> str,
                    'role'=> str,
                ],
            ],
        }
        */
        $path = 'organizations/accounts/';
        return $this->api->get($path);
    }

    # === Login ===

    function login_individual($email, $password, $db_id, $frontend_user_id=null)
    {
        /*
        Login on a database with an account

        :param str email:
        :param str password:
        :param str db_id:
        :param ID? frontend_user_id: user ID
        :returns: [
            'token'=> str,
            'database'=> [
                'id'=> str,
                'name'=> str,
                'description'=> str,
                'item_id_type'=> str,
                'user_id_type'=> str,
            ],
        ]
        */
        $path = 'login/individual/';
        $data = [
            'email'=> $email,
            'password'=> $password,
            'db_id'=> $db_id,
        ];
        return $this->_login($path, $data, $frontend_user_id);
    }

    function login_service($name, $password, $db_id, $frontend_user_id=null)
    {
        /*
        Login on a database with a service account

        :param str name:
        :param str password:
        :param str db_id:
        :param ID? frontend_user_id: user ID
        :returns: [
            'token'=> str,
            'database'=> [
                'id'=> str,
                'name'=> str,
                'description'=> str,
                'item_id_type'=> str,
                'user_id_type'=> str,
            ],
        ]
        */
        $path = 'login/service/';
        $data = [
            'name'=> $name,
            'password'=> $password,
            'db_id'=> $db_id,
        ];
        return $this->_login($path, $data, $frontend_user_id);
    }

    function login_root($email, $password)
    {    
        /*
        Login with the root account without selecting a database

        :param str email:
        :param str password:
        :returns: [
            'token'=> str,
        ]
        */
        $path = 'login/root/';
        $data = [
            'email'=> $email,
            'password'=> $password
        ];
        $resp = $this->api->post($path, $data);
        $jwt_token = $resp['token'];
        $this->set_jwt_token($jwt_token);
        $this->_database = null;
        $this->_refresh_token = null;
        return $resp;
    }

    function login_refresh_token($refresh_token=null)
    {
        /*
        Login again using a refresh token

        :param str? refresh_token: (default: $this->_refresh_token)
        :returns: [
            'token'=> str,
            'database'=> [
                'id'=> str,
                'name'=> str,
                'description=> str,
                'item_id_type'=> str,
                'user_id_type'=> str,
            ],
        ]
        */
        $refresh_token = $refresh_token ?? $this->_refresh_token;
        $path = 'login/refresh-token/';
        $data = [
            'refresh_token'=> $refresh_token,
        ];
        return $this->_login($path, $data, null);
    }
    
    function _login($path, $data, $frontend_user_id)
    {
        if ($frontend_user_id)
            $data['frontend_user_id'] = $this->_userid2body($frontend_user_id);
        $resp = $this->api->post($path, $data);
        $jwt_token = $resp['token'];
        $this->set_jwt_token($jwt_token);
        $this->_database = $resp['database'];
        $this->_refresh_token = $resp['refresh_token'];
        return $resp;
    }

    # === Org metadata ===

    function get_organization()
    {
        /*
        get organization meta-data
        */
        $path = 'organizations/current/';
        return $this->api->get($path);
    }

    function create_or_partial_update_organization($metadata, $preserve=null)
    {
        /*
        create, or apply deep partial update of meta-data
        :param array metadata: meta-data to store structured as unvalidated JSON-compatible array
        :param bool? preserve: set to `True` to append values instead of replace as in RFC7396
        */
        $path = 'organizations/current/';
        $data = ['metadata'=> $metadata];
        if ($preserve != null)
            $data['preserve'] = $preserve;
        return $this->api->patch($path, $data);
    }

    function partial_update_organization_preview($metadata, $preserve=null)
    {
        /*
        preview deep partial update of extra data, without changing any state
        :param array metadata: extra meta-data to store structured as unvalidated JSON-compatible array
        :param bool? preserve: set to `True` to append values instead of replace as in RFC7396
        :returns: [
            'metadata_old': [
                'description'=> str,
                'extra'=> {**any-key=> any-val},
            ],
            'metadata_new': [
                'description'=> str,
                'extra'=> {**any-key=> any-val},
            ],
        ]
        */
        $path = 'organizations/current/preview/';
        $data = ['metadata'=> $metadata];
        if ($preserve != null)
            $data['preserve'] = $preserve;
        return $this->api->patch($path, $data);
    }

    # === Database ===

    function create_database($name, $description, $item_id_type='uint32', $user_id_type='uint32')
    {
        /*
        Create a new database

        :param str name: Database name, must be unique
        :param str description:
        :param str item_id_type: Item ID type
        :param str user_id_type: User ID type
        */
        $path = 'databases/';
        $data = [
            'name'=> $name,
            'description'=> $description,
            'item_id_type'=> $item_id_type,
            'user_id_type'=> $user_id_type,
        ];
        return $this->api->post($path, $data);
    }

    function get_all_databases($amt=null, $page=null)
    {
        /*
        Get all databases on the current organization

        :param int? amt: amount of databases by page (default: API default)
        :param int? page: page number (default: 1)
        :returns: [
            'has_next'=> bool,
            'next_page'=> int,
            'databases'=> [
                [
                    'id'=> int,
                    'name'=> str,
                    'description'=> str,
                    'item_id_type'=> str,
                    'user_id_type'=> str
                ],
            ]
        ]
        */
        $path = 'databases/';
        $params = [];
        if ($amt)
            $params['amt'] = amt;
        if ($page)
            $params['page'] = page;
        return $this->api->get($path, $params);
    }

    function get_database()
    {
        /*
        Get details on current database

        :returns: [
            'id'=> str,
            'name'=> str,
            'description'=> str,
            'item_id_type'=> str,
            'user_id_type'=> str,
            'counters'=> {
                'rating'=> int,
                'user'=> int,
                'item'=> int,
            ],
            'metadata'=> {**any-key=> any-val},
        ]
        */
        $path = 'databases/current/';
        return $this->api->get($path);
    }

    function partial_update_database($description=null, $metadata=null, $preserve=null)
    {
        /*
        update description, and apply deep partial update of extra meta-data
        :param str? description: description of DB
        :param array? metadata: extra data to store structured as unvalidated JSON-compatible array
        :param bool? preserve: set to `True` to append values instead of replace as in RFC7396
        */
        $path = 'databases/current/';
        $data = [];
        assert ($description != null or $metadata != null);
        if ($description != null)
            $data['description'] = $description;
        if ($metadata != null)
            $data['metadata'] = $metadata;
        if ($preserve != null)
            $data['preserve'] = $preserve;
        return $this->api->patch($path, $data);
    }

    function partial_update_database_preview($description=null, $metadata=null, $preserve=null)
    {
        /*
        preview deep partial update of extra data, without changing any state
        :param str? description: description of DB
        :param array? metadata: extra data to store structured as unvalidated JSON-compatible array
        :param bool? preserve: set to `True` to append values instead of replace as in RFC7396
        :returns: [
            'metadata_old'=> [
                'description'=> str,
                'metadata'=> {**any-key=> any-val},
            ],
            'metadata_new'=> [
                'description'=> str,
                'metadata'=> {**any-key=> any-val},
            ],
        ]
        */
        $path = 'databases/current/preview/';
        $data = [];
        assert ($description != null or $metadata != null);
        if ($description != null)
            $data['description'] = $description;
        if ($metadata != null)
            $data['metadata'] = $metadata;
        if ($preserve != null)
            $data['preserve'] = $preserve;
        return $this->api->patch($path, $data);
    }

    function delete_database()
    {
        /*
        Delete current database.
        */
        $path = 'databases/current/';
        return $this->api->delete($path, [], ['timeout'=>29]);
    }

    function status()
    {
        /*
        Get readiness status of current database.
        */
        $path = 'databases/current/status/';
        return $this->api->get($path);
    }

    # === User Property ===

    function get_user_property($property_name)
    {
        /*
        Get one user-property.

        :param str property_name: property name
        :returns: [
            'property_name'=> str,
            'value_type'=> str,
            'repeated'=> bool,
        ]
        */
        $path = 'users-properties/'.$this->escape_url($property_name).'/';
        return $this->api->get($path);
    }

    function list_user_properties()
    {
        /*
        Get all user-properties for the current database.

        :returns: [
            'properties'=> [[
                'property_name'=> str,
                'value_type'=> str,
                'repeated'=> bool,
            ]],
        ]
        */
        $path = 'users-properties/';
        return $this->api->get($path);
    }

    function create_user_property($property_name, $value_type, $repeated=False)
    {
        /*
        Create a new user-property.

        :param str property_name: property name
        :param str value_type: property type
        :param bool? repeated: whether the property has many values (default: False)
        */
        $path = 'users-properties/';
        $data = [
            'property_name'=> $property_name,
            'value_type'=> $value_type,
            'repeated'=> $repeated,
        ];
        return $this->api->post($path, $data);
    }

    function delete_user_property($property_name)
    {
        /*
        Delete an user-property given by its name

        :param str property_name: property name
        */
        $path = 'users-properties/'.$this->escape_url($property_name).'/';
        return $this->api->delete($path);
    }

    # === User ===

    function get_user($user_id)
    {
        /*
        Get one user given its ID.

        :param ID user_id: user ID
        :returns: [
            'item'=> [
                'id'=> ID,
                *<property_name=> property_value>,
            ]
        ]
        */
        $user_id = $this->_userid2url($user_id);
        $path = "users/{$user_id}/";
        $resp = $this->api->get($path);
        $resp['user']['user_id'] = $this->_body2userid($resp['user']['user_id']);
        return $resp;
    }

    function list_users_paginated($amt=null, $cursor=null)
    {
        /*
        Get multiple users by page.
        The response is paginated, you can control the response amount and offset
        using the query parameters ``amt`` and ``cursor``.

        :param int? amt: amount to return (default: use the API default)
        :param str? cursor: Pagination cursor
        :returns: [
            'users'=> array with fields ['id'=> ID, *<property_name: value_type>]
                contains only the non-repeated values,
            'users_m2m'=> array of arrays for repeated values:
                {
                    *<repeated_property_name=> {
                        'name'=> str,
                        'array'=> array with fields ['user_index': uint32, 'value_id': value_type],
                    }>
                },
            'has_next'=> bool,
            'next_cursor'=> str, pagination cursor to use in next request to get more users,
        ]
        */
        $path = 'users-bulk/';
        $params = [];
        if ($amt)
            $params['amt'] = $amt;
        if ($cursor)
            $params['cursor'] = $cursor;
        $resp = $this->api->get($path, $params);
        $resp['users'] = $this->_body2userid($resp['users']);
        return $resp;
    }

    //require_login
    function create_or_update_user($user)
    {
        /*
        Create a new user, or update it if the ID already exists.

        :param array user: user ID and properties {'user_id': ID, *<property_name: property_value>}
        */
        $user = (array)($user);
        $user_id = $this->_userid2url($user['user_id']);
        unset($user['user_id']);
        $path = "users/{$user_id}/";
        $data = [
            'user'=> $user,
        ];
        return $this->api->put($path, $data);
    }

    function create_or_update_users_bulk($users, $chunk_size=(1<<10))
    {
        /*
        Create many users in bulk, or update the ones for which the id already exist.

        :param array users: array with fields ['id'=> ID, *<property_name=> value_type>]
            contains only the non-repeated values,
        :param int? chunk_size: split the requests in chunks of this size (default: 1K)
        */
        $path = 'users-bulk/';
        $chunked = $this->_chunk_users($users, $chunk_size);
        foreach ($chunked as list($users_chunk, $users_m2m_chunk))
        {
            $data = [
                'users'=> $users_chunk,
                'users_m2m'=> $users_m2m_chunk
            ];
            $this->api->put($path, $data, ['timeout'=>60]);
        }
    }

    function partial_update_user($user, $create_if_missing=null)
    {
        /*
        Partially update some properties of an user

        :param array user: user ID and properties ['user_id'=> ID, *<property_name=> property_value>]
        :param bool? create_if_missing: control whether an error should be returned or a new user
        should be created when the ``user_id`` does not already exist. (default: False)
        */
        $user = (array)($user);
        $user_id = $this->_userid2url($user['user_id']);
        unset($user['user_id']);
        $path = "users/{$user_id}/";
        $data = [
            'user'=> $user,
        ];
        if ($create_if_missing != null)
            $data['create_if_missing'] = $create_if_missing;
        return $this->api->patch($path, $data);
    }

    function partial_update_users_bulk($users, $create_if_missing=null,
                                  $chunk_size=(1 << 10))
    {
        /*
        Partially update some properties of many users.

        :param array users: array with fields ['id'=> ID, *<property_name=> value_type>]
            contains only the non-repeated values,
        :param bool? create_if_missing: to control whether an error should be returned or new users
        should be created when the ``user_id`` does not already exist. (default: False)
        :param int? chunk_size: split the requests in chunks of this size (default: 1K)
        */
        $path = 'users-bulk/';
        $data = [];
        if ($create_if_missing != null)
            $data['create_if_missing'] = $create_if_missing;
        $chunked = $this->_chunk_users($users, $chunk_size);
        foreach ($chunked as list($users_chunk, $users_m2m_chunk))
        {
            $data['users'] = $users_chunk;
            $data['users_m2m'] = $users_m2m_chunk;
            $this->api->patch($path, $data, ['timeout'=>60]);
        }
    }

    function _chunk_users($users, $chunk_size)
    {
        $n_chunks = (int)(ceil(count($users) / $chunk_size));
        $out = [];
        for ($i = 0; $i < $n_chunks; $i++)
        {
            $start_idx = $i * $chunk_size;
            $end_idx = ($i + 1) * $chunk_size;
            $users_chunk = array_slice($users, $start_idx, $chunk_size);
            array_push($out, [$this->_userid2body($users_chunk), []]);
        }
        return $out;
    }

    # === Item Property ===

    function get_item_property($property_name)
    {
        /*
        Get one item-property.

        :param str property_name: property name
        :returns: [
            'property_name'=> str,
            'value_type'=> str,
            'repeated'=> bool,
        ]
        */
        $path = "items-properties/{$this->escape_url($property_name)}/";
        return $this->api->get($path);
    }

    function list_item_properties()
    {
        /*
        Get all item-properties for the current database.

        :returns: [
            'properties'=> [[
                'property_name'=> str,
                'value_type'=> str,
                'repeated'=> bool,
            ]],
        ]
        */
        $path = 'items-properties/';
        return $this->api->get($path);
    }

    function create_item_property($property_name, $value_type, $repeated=False)
    {
        /*
        Create a new item-property.

        :param str property_name: property name
        :param str value_type: property type
        :param bool? repeated: whether the property has many values (default: False)
        */
        $path = 'items-properties/';
        $data = [
            'property_name'=> $property_name,
            'value_type'=> $value_type,
            'repeated'=> $repeated,
        ];
        return $this->api->post($path, $data);
    }

    function delete_item_property($property_name)
    {
        /*
        Delete an item-property given by its name

        :param str property_name: property name
        */
        $path = "items-properties/{$this->escape_url($property_name)}/";
        return $this->api->delete($path);
    }

    # === Item ===

    function get_item($item_id)
    {
        /*
        Get one item given its ID.

        :param ID item_id: item ID
        :returns: [
            'item'=> [
                'id'=> ID,
                *<property_name=> property_value>,
            ]
        ]
        */
        $item_id = $this->_itemid2url($item_id);
        $path = "items/{$item_id}/";
        $resp = $this->api->get($path);
        $resp['item']['item_id'] = $this->_body2itemid($resp['item']['item_id']);
        return $resp;
    }

    function list_items($items_id)
    {
        /*
        Get multiple items given their IDs.
        The items in the response are not aligned with the input.
        In particular this endpoint does not raise NotFoundError if any item in missing.
        Instead, the missing items are simply not present in the response.

        :param ID-array items_id: items IDs
        :returns: [
            'items'=> array with fields ['id': ID, *<property_name: value_type>]
                contains only the non-repeated values,
            'items_m2m'=> array of arrays for repeated values:
                [
                    *<repeated_property_name: [
                        'name'=> str,
                        'array'=> array with fields ['item_index'=> uint32, 'value_id'=> value_type],
                    ]>
                ]
        ]
        */
        $items_id = $this->_itemid2body($items_id);
        $path = 'items-bulk/list/';
        $data = ['items_id'=> $items_id];
        $resp = $this->api->post($path, $data);
        $resp['items'] = $this->_body2itemid($resp['items']);
        return $resp;
    }

    function list_items_paginated($amt=null, $cursor=null)
    {
        /*
        Get multiple items by page.
        The response is paginated, you can control the response amount and offset
        using the query parameters ``amt`` and ``cursor``.

        :param int? amt: amount to return (default: use the API default)
        :param str? cursor: Pagination cursor
        :returns: [
            'items'=> array with fields ['id'=> ID, *<property_name=> value_type>]
                contains only the non-repeated values,
            'items_m2m'=> array of arrays for repeated values:
                [
                    *<repeated_property_name=> {
                        'name'=> str,
                        'array'=> array with fields ['item_index'=> uint32, 'value_id'=> value_type],
                    }>
                ],
            'has_next'=> bool,
            'next_cursor'=> str, pagination cursor to use in next request to get more items,
        ]
        */
        $path = 'items-bulk/';
        $params = [];
        if ($amt != null)
            $params['amt'] = $amt;
        if ($cursor != null)
            $params['cursor'] = $cursor;
        $resp = $this->api->get($path, $params);
        $resp['items'] = $this->_body2itemid($resp['items']);
        return $resp;
    }

    function create_or_update_item($item)
    {
        /*
        Create a new item, or update it if the ID already exists.

        :param array item: item ID and properties ['item_id'=> ID, *<property_name=> property_value>]
        */
        $item = (array)$item;
        $item_id = $this->_itemid2url($item['item_id']);
        unset($item['item_id']);
        $path = "items/{$item_id}/";
        $data = [
            'item'=> $item,
        ];
        return $this->api->put($path, $data);
    }

    function create_or_update_items_bulk($items, $chunk_size=(1<<10))
    {
        /*
        Create many items in bulk, or update the ones for which the id already exist.

        :param array items: array with fields ['id'=> ID, *<property_name=> value_type>]
            contains only the non-repeated values,
        :param int? chunk_size: split the requests in chunks of this size (default: 1K)
        */
        $path = 'items-bulk/';
        $chunks = $this->_chunk_items($items, $chunk_size);
        foreach ($chunks as list($items_chunk, $items_m2m_chunk))
        {
            $data = [
                'items'=> $items_chunk,
                'items_m2m'=> $items_m2m_chunk,
            ];
            $this->api->put($path, $data, $timeout=60);
        }
    }

    function partial_update_item($item, $create_if_missing=null)
    {
        /*
        Partially update some properties of an item.

        :param array item: item ID and properties ['item_id'=> ID, *<property_name=> property_value>]
        :param bool? create_if_missing: control whether an error should be returned or a new item
        should be created when the ``item_id`` does not already exist. (default: false)
        */
        $item = (array)$item;
        
        $item_id = $this->_itemid2url($item['item_id']);
        unset($item['item_id']);
        $path = "items/{$item_id}/";
        $data = [
            'item'=> $item,
        ];
        if ($create_if_missing != null)
            $data['create_if_missing'] = $create_if_missing;
        return $this->api->patch($path, $data);
    }

    function partial_update_items_bulk($items, $create_if_missing=null,
                                  $chunk_size=(1 << 10))
    {
        /*
        Partially update some properties of many items.

        :param array items: array with fields ['id'=> ID, *<property_name=> value_type>]
            contains only the non-repeated values,
        :param bool? create_if_missing: control whether an error should be returned or a new item
        should be created when the ``item_id`` does not already exist. (default: false)
        :param int? chunk_size: split the requests in chunks of this size (default: 1K)
        */
        $path = 'items-bulk/';
        $data = [];
        if ($create_if_missing != null)
            $data['create_if_missing'] = $create_if_missing;
        $chunks = $this->_chunk_items($items, $chunk_size);
        foreach ($chunks as list($items_chunk, $items_m2m_chunk))
        {
            $data['items'] = $items_chunk;
            $data['items_m2m'] = $items_m2m_chunk;
            $this->api->patch($path, $data, $timeout=60);
        }
    }

    function _chunk_items($items, $chunk_size)
    {
        // cast array to list of array
        $n_chunks = (int)(ceil(count($items) / $chunk_size));
        $out = [];
        for ($i=0; $i<$n_chunks; $i++)
        {
            $start_idx = $i * $chunk_size;
            $end_idx = ($i + 1) * $chunk_size;
            $items_chunk = array_slice($items, $start_idx, $chunk_size);
            $items_m2m_chunk = [];
            array_push($out, [$this->_itemid2body($items_chunk), $items_m2m_chunk]);
        }
        return $out;
    }

    # === Reco: Item-to-item ===

    function get_reco_item_to_items($item_id, $amt=null, $cursor=null, $filters=null)
    {
        /*
        Get similar items.

        :param ID item_id: item ID
        :param int? amt: amount to return (default: use the API default)
        :param str? cursor: Pagination cursor
        :param list-str? filters: Filter by properties. Filter format: ['<PROP_NAME>:<OPERATOR>:<OPTIONAL_VALUE>',...]
        :returns: [
            'items_id'=> array of items IDs,
            'next_cursor'=> str, pagination cursor to use in next request to get more items,
        ]
        */
        $item_id = $this->_itemid2url($item_id);
        $path = "recommendation/items/{$item_id}/items/";
        $params = [];
        if ($amt)
            $params['amt'] = $amt;
        if ($cursor)
            $params['cursor'] = $cursor;
        if ($filters)
            $params['filters'] = $filters;
        $resp = $this->api->get($path, $params);
        $resp['items_id'] = $this->_body2itemid($resp['items_id']);
        return $resp;
    }

    # === Reco: Session-to-item ===

    function get_reco_session_to_items($ratings=null, $user_properties=null,
                                  $amt=null, $cursor=null, $filters=null, $exclude_rated_items=null)
    {
        /*
        Get items recommendations given the ratings of an anonymous session.

        :param array? ratings: ratings array with fields ['item_id': ID, 'rating': float]
        :param array? user_properties: user properties {**property_name: property_value(s)}
        :param int? amt: amount to return (default: use the API default)
        :param str? cursor: Pagination cursor
        :param list-str? filters: Filter by properties. Filter format: ['<PROP_NAME>:<OPERATOR>:<OPTIONAL_VALUE>',...]
        :param bool? exclude_rated_items: exclude rated items from response
        :returns: [
            'items_id'=> array of items IDs,
            'next_cursor'=> str, pagination cursor to use in next request to get more items,
        ]
        */
        $path = 'recommendation/sessions/items/';
        $data = [];
        if ($ratings != null)
            $data['ratings'] = $this->_itemid2body($ratings);
        if ($user_properties)
            $data['user_properties'] = $user_properties;
        if ($amt)
            $data['amt'] = $amt;
        if ($cursor)
            $data['cursor'] = $cursor;
        if ($filters)
            $data['filters'] = $filters;
        if ($exclude_rated_items != null)
            $data['exclude_rated_items'] = $exclude_rated_items;
        $resp = $this->api->post($path, $data);
        $resp['items_id'] = $this->_body2itemid(resp['items_id']);
        return $resp;
    }

    # === Reco: User-to-item ===

    function get_reco_user_to_items($user_id, $amt=null, $cursor=null, $filters=null,
                               $exclude_rated_items=null)
    {
        /*
        Get items recommendations given a user ID.

        :param ID user_id: user ID
        :param int? amt: amount to return (default: use the API default)
        :param str? cursor: Pagination cursor
        :param list-str? filters: Filter by properties. Filter format: ['<PROP_NAME>:<OPERATOR>:<OPTIONAL_VALUE>',...]
        :param bool? exclude_rated_items: exclude rated items from response
        :returns: [
            'items_id'=> array of items IDs,
            'next_cursor'=> str, pagination cursor to use in next request to get more items,
        ]
        */
        $user_id = $this->_userid2url($user_id);
        $path = "recommendation/users/{$user_id}/items/";
        $params = [];
        if ($amt)
            $params['amt'] = $amt;
        if ($cursor)
            $params['cursor'] = $cursor;
        if ($filters)
            $params['filters'] = $filters;
        if ($exclude_rated_items != null)
            $params['exclude_rated_items'] = $exclude_rated_items;
        $resp = $this->api->get($path, $params);
        $resp['items_id'] = $this->_body2itemid($resp['items_id']);
        return $resp;
    }

    # === User Ratings ===

    function create_or_update_rating($user_id, $item_id, $rating, $timestamp=null)
    {
        /*
        Create or update a rating for a user and an item.
        If the rating exists for the tuple (user_id, item_id) then it is updated,
        otherwise it is created.

        :param ID user_id: user ID
        :param ID item_id: item ID
        :param float rating: rating value
        :param float? timestamp: rating timestamp (default: now)
        */
        $user_id = $this->_userid2url($user_id);
        $item_id = $this->_itemid2url($item_id);
        $path = "users/{$user_id}/ratings/{$item_id}/";
        $data = [
            'rating'=> $rating,
        ];
        if ($timestamp != null)
            $data['timestamp'] = $timestamp;
        return $this->api->put($path, $data);
    }

    function create_or_update_user_ratings_bulk($user_id, $ratings)
    {
        /*
        Create or update bulks of ratings for a single user and many items.

        :param ID user_id: user ID
        :param array ratings: ratings array with fields:
            ['item_id'=> ID, 'rating'=> float, 'timestamp'=> float]
        */
        $user_id = $this->_userid2url($user_id);
        $path = "users/{$user_id}/ratings/";
        $data = [
            'ratings'=> $this->_itemid2body($ratings),
        ];
        return $this->api->put($path, $data, ['timeout'=>10]);
    }

    function create_or_update_ratings_bulk($ratings, $chunk_size=(1<<14))
    {    
        /*
        Create or update large bulks of ratings for many users and many items.

        :param array ratings: ratings array with fields:
            ['user_id'=> ID, 'item_id'=> ID, 'rating'=> float, 'timestamp'=> float]
        :param int? chunk_size: split the requests in chunks of this size (default: 16K)
        */
        $path = 'ratings-bulk/';
        $n_chunks = (int)(ceil(count($ratings) / $chunk_size));
        for ($i=0; $i<$n_chunks; $i++)
        {
            $ratings_chunk = array_slice($ratings, $i*$chunk_size, $chunk_size);
            $ratings_chunk = $this->_userid2body($this->_itemid2body($ratings_chunk));
            $data = [
                'ratings'=> $ratings_chunk,
            ];
            $this->api->put($path, $data, ['timeout'=>60]);
        }
        return;
    }

    function list_user_ratings($user_id, $amt=null, $page=null)
    {
        /*
        List the ratings of one user (paginated)

        :param ID user_id: user ID
        :param int? amt: amount of ratings by page (default: API default)
        :param int? page: page number (default: 1)
        :returns: [
            'has_next'=> bool,
            'next_page'=> int,
            'user_ratings'=> ratings array with fields
                ['item_id'=> ID, 'rating'=> float, 'timestamp'=> float]
        ]
        */
        $user_id = $this->_userid2url($user_id);
        $path = "users/{$user_id}/ratings/";
        $params = [];
        if ($amt)
            $params['amt'] = $amt;
        if ($page)
            $params['page'] = $page;
        $resp = $this->api->get($path, $params);
        $resp['user_ratings'] = $this->_body2itemid($resp['user_ratings']);
        return $resp;
    }

    function list_ratings($amt=null, $cursor=null)
    {
        /*
        List the ratings of one database

        :param int? amt: amount to return (default: use the API default)
        :param str? cursor: Pagination cursor
        :returns: [
            'has_next'=> bool,
            'next_cursor'=> str,
            'ratings'=> array with fields
                ['item_id'=> ID, 'user_id'=> ID, 'rating'=> float, 'timestamp'=> float]
        ]
        */
        $path = 'ratings-bulk/';
        $params = [];
        if ($amt)
            $params['amt'] = $amt;
        if ($cursor)
            $params['cursor'] = $cursor;
        $resp = $this->api->get($path, $params);
        $resp['ratings'] = $this->_body2userid($this->_body2itemid($resp['ratings']));
        return resp;
    }

    function delete_rating($user_id, $item_id)
    {
        /*
        Delete a single rating for a given user.

        :param ID user_id: user ID
        :param ID item_id: item ID
        */
        $user_id = $this->_userid2url($user_id);
        $item_id = $this->_itemid2url($item_id);
        $path = "users/{$user_id}/ratings/{$item_id}";
        return $this->api->delete($path);
    }

    function delete_user_ratings($user_id)
    {
        /*
        Delete all ratings of a given user.

        :param ID user_id: user ID
        */
        $user_id = $this->_userid2url($user_id);
        $path = "users/{$user_id}/ratings/";
        return $this->api->delete($path);
    }

    # === User Interactions ===

    function create_interaction($user_id, $item_id, $interaction_type, $timestamp=null)
    {
        /*
        This endpoint allows you to create a new interaction for a user and an item.
        An inferred rating will be created or updated for the tuple (user_id, item_id).
        The taste profile of the user will then be updated in real-time by the online machine learning algorithm.

        :param ID user_id: user ID
        :param ID item_id: item ID
        :param str interaction_type: Interaction type
        :param float? timestamp: rating timestamp (default: now)
        */
        $user_id = $this->_userid2url($user_id);
        $item_id = $this->_itemid2url($item_id);
        $path = "users/{$user_id}/interactions/{$item_id}/";
        $data = [
            'interaction_type'=> $interaction_type,
        ];
        if ($timestamp != null)
            $data['timestamp'] = $timestamp;
        return $this->api->post($path, $data);
    }

    function create_interactions_bulk($interactions, $chunk_size=(1<<14))
    {
        /*
        Create or update large bulks of interactions for many users and many items.
        Inferred ratings will be created or updated for all tuples (user_id, item_id).

        :param array interactions: interactions array with fields:
            ['user_id'=> ID, 'item_id'=> ID, 'interaction_type'=> str, 'timestamp'=> float]
        :param int? chunk_size: split the requests in chunks of this size (default: 16K)
        */
        $path = 'interactions-bulk/';
        $n_chunks = (int)(ceil(count($interactions) / $chunk_size));
        for ($i=0; $i<$n_chunks; $i++)
        {
            $interactions_chunk = array_slice($interactions, $i*$chunk_size, $chunk_size);
            $interactions_chunk = $this->_userid2body($this->_itemid2body($interactions_chunk));
            $data = [
                'interactions'=> $interactions_chunk,
            ];
            $this->api->post($path, $data, ['timeout'=>10]);
        }
        return;
    }

    # === Data Dump Storage ===

    function get_data_dump_signed_urls($name, $content_type, $resource)
    {
        /*
        Get signed url to upload a file. (url_upload and url_report)

        :param str? name: filename
        :param str? content_type:
        :param str? resource: values allowed are `items`, `users`, `ratings` and `ratings_implicit`.
        :returns: [
            'url_upload'=> str,
            'url_report'=> str,
        ]
        */
        $path = 'data-dump-storage/signed-url/';
        $params = ['name'=> $name,
                  'content_type'=> $content_type,
                  'resource'=> $resource];
        return $this->api->get($path, $params);
    }

    # === Scheduled Background Tasks ===

    function trigger_background_task($task_name, $payload=null)
    {
        /*
        Trigger background task such as retraining of ML models.
        You should not have to call this endpoint yourself, as this is done automatically.

        :param str task_name: for instance ``'ml_model_retrain'``
        :param array? payload: optional task payload
        :returns: [
            'task_id'=> str,
        ]
        :raises: DuplicatedError with error name 'TASK_ALREADY_RUNNING'
            if this task is already running
        */
        $path = "tasks/{$this->escape_url($task_name)}/";
        $data = [];
        if ($payload)
            $data['payload'] = $payload;
        return $this->api->post($path, $data);
    }

    function get_background_tasks($task_name)
    {
        /*
        List currently running background tasks such as ML models training.

        :param str task_name: for instance ``'ml_model_retrain'``
        :returns: {
            'tasks'=> [[
                'name'=> string, Task name
                'start_time'=> int, Start timestamp
                'details'=> array, Execution details, like progress message
            ]],
        }
        */
        $path = "tasks/{$this->escape_url($task_name)}/recents/";
        return $this->api->get($path);
    }

    # === Utils ===

    function _str_starts_with( $haystack, $needle ) 
    {
        // https://stackoverflow.com/a/834355/4288232
        $length = strlen( $needle );
        return substr( $haystack, 0, $length ) === $needle;
    }

    function _multi_str_starts_with($haystack, $needles)
    {
        foreach ($needles as $needle)
            if($this->_str_starts_with($haystack, $needle)) return true;
        return false;
    }

    function clear_jwt_token()
    {
        return $this->api->clear_jwt_token();
    }

    function jwt_token()
    {
        return $this->api->jwt_token();
    }

    function set_jwt_token($jwt_token)
    {
        $this->api->set_jwt_token($jwt_token);
    }

    function _userid2url($user_id)
    {
        // base64 encode if needed
        return $this->_id2url($user_id, 'user');
    }
    
    function _itemid2url($item_id)
    {
        // base64 encode if needed
        return $this->_id2url($item_id, 'item');
    }

    function _id2url($data, $field)
    {
        // base64 encode if needed
        if ($this->_str_starts_with($this->_database[$field.'_id_type'], 'bytes'))
            return $this->_b64_encode($data);
        //if isinstance(data, bytes):
        //    return data.decode('ascii')
        return $data;
    }

    function _userid2body($data)
    {
        return $this->_base_field_id($data, 'user', '$this->_body2id');
    }

    function _itemid2body($data)
    {
        return $this->_base_field_id($data, 'item', '$this->_body2id');
    }

    function _body2userid($data)
    {
        return $this->_base_field_id($data, 'user', '$this->_body2id');
    }

    function _body2itemid($data)
    {
        return $this->_base_field_id($data, 'item', '$this->_body2id');
    }

    function _base_field_id($data, $field, $cast_func)
    {
        if (!$this->b64_encode_bytes)
            return $data;
        $d_type = $this->_database["{$field}_id_type"];
        if ($this->_multi_str_starts_with($d_type, ['bytes', 'uuid', 'hex', 'urlsafe']))
        {
            if (is_array($data))
            {
                $allMembersAreArrays = true;
                foreach ($data as $d)
                {
                    if (!is_array($d))
                    {
                        $allMembersAreArrays = false;
                        break;
                    }
                }

                if($allMembersAreArrays)
                {
                    $data = [];
                    foreach ($data as $row)
                    {
                        $rowOut = $row;
                        $rowOut["{$field}_id"] = $cast_func($row["{$field}_id"], $d_type);
                    }
                }
                else 
                {
                    $data = [];
                    foreach ($data as $row)
                        array_push($data, $cast_func($row, $d_type));
                }
            }
            else
                $data = $cast_func($data, $d_type);
        }
        return $data;
    }

    function _id2body($data, $d_type)
    {
        if ($this->_multi_str_starts_with($d_type, ['uuid', 'hex', 'urlsafe']))
            return $data;
        else  # Bytes
            return $this->_b64_encode($data);
    }

    function _body2id($data, $d_type)
    {
        if ($this->_multi_str_starts_with($d_type, ['uuid', 'hex', 'urlsafe']))
            return $data;
        else  # Bytes
            return $this->_b64_decode($data);
    }

    function _b64_encode($data)
    {
        $out = base64_encode($data);
        $out = str_replace('+', '-', $out); //Encode like python's urlsafe_b64encode
        $out = str_replace('_', '/', $out);
        $out = str_replace('=', '', $out);
        return $out;
    }

    function _b64_decode($data)
    {
        $data = str_replace('-', '+', $data); //Decode like python's urlsafe_b64decode
        $data = str_replace('/', '_', $data);
        $n_pad = (4 - strlen($data) % 4) % 4;
        if ($n_pad <= 2)
            $data = $data . str_repeat('=', $n_pad);
        else if ($n_pad == 3)
            throw TypeError();  // TODO
        try
        {
            return base64_decode($data);
        }
        catch (Exception $err)
        {
            throw TypeError();
        }
    }

    function escape_url($param)
    {
        return urlencode($param);
    }

}

// ************************************************************

function RequireLogin($obj, $method, $args)
{
    try    {
        return call_user_func_array([$obj, $method], $args);
    }
    catch (JwtTokenExpired $err) {
        if (!$this->_refresh_token or !$this->auto_refresh_token)
            throw $err;
        $this->login_refresh_token();
        return call_user_func_array([$obj, $method], $args);
    }
}

class CrossingMindsApiClient extends CrossingMindsApiClientImpl
{
    /*
    The client handles the logic to automatically get a new JWT token using the refresh token
    */

    function __construct($api_kwargs)
    {
        parent::__construct($api_kwargs);
    }

    function create_individual_account($first_name, $last_name, $email, $password, $role='backend')
    {
        return RequireLogin($this, 'parent::create_individual_account', func_get_args());
    }

    function create_service_account($name, $password, $role='frontend')
    {
        return RequireLogin($this, 'parent::create_service_account', func_get_args());
    }

    function list_accounts()
    {
        return RequireLogin($this, 'parent::list_accounts', func_get_args());
    }

    function get_organization()
    {
        return RequireLogin($this, 'parent::get_organization', func_get_args());
    }

    function create_or_partial_update_organization($metadata, $preserve=null)
    {
        return RequireLogin($this, 'parent::create_or_partial_update_organization', func_get_args());
    }

    function partial_update_organization_preview($metadata, $preserve=null)
    {
        return RequireLogin($this, 'parent::partial_update_organization_preview', func_get_args());
    }

    function create_database($name, $description, $item_id_type='uint32', $user_id_type='uint32')
    {
        return RequireLogin($this, 'parent::create_database', func_get_args());
    }

    function get_all_databases($amt=null, $page=null)
    {
        return RequireLogin($this, 'parent::get_all_databases', func_get_args());
    }

    function get_database()
    {
        return RequireLogin($this, 'parent::get_database', func_get_args());
    }

    function partial_update_database($description=null, $metadata=null, $preserve=null)
    {
        return RequireLogin($this, 'parent::partial_update_database', func_get_args());
    }

    function partial_update_database_preview($description=null, $metadata=null, $preserve=null)
    {
        return RequireLogin($this, 'parent::partial_update_database_preview', func_get_args());
    }

    function delete_database()
    {
        return RequireLogin($this, 'parent::delete_database', func_get_args());
    }

    function status()
    {
        return RequireLogin($this, 'parent::status', func_get_args());
    }

    function get_user_property($property_name)
    {
        return RequireLogin($this, 'parent::get_user_property', func_get_args());
    }

    function list_user_properties()
    {
        return RequireLogin($this, 'parent::list_user_properties', func_get_args());
    }

    function create_user_property($property_name, $value_type, $repeated=False)
    {
        return RequireLogin($this, 'parent::create_user_property', func_get_args());
    }

    function delete_user_property($property_name)
    {
        return RequireLogin($this, 'parent::delete_user_property', func_get_args());
    }

    function get_user($user_id)
    {
        return RequireLogin($this, 'parent::get_user', func_get_args());
    }

    function list_users_paginated($amt=null, $cursor=null)
    {
        return RequireLogin($this, 'parent::list_users_paginated', func_get_args());
    }

    function create_or_update_user($user)
    {
        return RequireLogin($this, 'parent::create_or_update_user', func_get_args());
    }

    function create_or_update_users_bulk($users, $chunk_size=(1<<10))
    {
        return RequireLogin($this, 'parent::create_or_update_users_bulk', func_get_args());
    }

    function partial_update_user($user, $create_if_missing=null)
    {
        return RequireLogin($this, 'parent::partial_update_user', func_get_args());
    }

    function partial_update_users_bulk($users, $create_if_missing=null,
                                  $chunk_size=(1 << 10))
    {
        return RequireLogin($this, 'parent::partial_update_users_bulk', func_get_args());
    }

    function get_item_property($property_name)  
    {
        return RequireLogin($this, 'parent::get_item_property', func_get_args());
    }

    function list_item_properties()
    {
        return RequireLogin($this, 'parent::list_item_properties', func_get_args());
    }

    function create_item_property($property_name, $value_type, $repeated=False)
    {
        return RequireLogin($this, 'parent::create_item_property', func_get_args());
    }

    function delete_item_property($property_name)
    {
        return RequireLogin($this, 'parent::delete_item_property', func_get_args());
    }

    function get_item($item_id)
    {
        return RequireLogin($this, 'parent::get_item', func_get_args());
    }

    function list_items($items_id)
    {
        return RequireLogin($this, 'parent::list_items', func_get_args());
    }

    function list_items_paginated($amt=null, $cursor=null)
    {
        return RequireLogin($this, 'parent::list_items_paginated', func_get_args());
    }

    function create_or_update_item($item)
    {
        return RequireLogin($this, 'parent::create_or_update_item', func_get_args());
    }

    function create_or_update_items_bulk($items, $chunk_size=(1<<10))
    {
        return RequireLogin($this, 'parent::create_or_update_items_bulk', func_get_args());
    }

    function partial_update_item($item, $create_if_missing=null)
    {
        return RequireLogin($this, 'parent::partial_update_item', func_get_args());
    }

    function partial_update_items_bulk($items, $create_if_missing=null,
                                  $chunk_size=(1 << 10))
    {
        return RequireLogin($this, 'parent::partial_update_items_bulk', func_get_args());
    }

    function get_reco_item_to_items($item_id, $amt=null, $cursor=null, $filters=null)
    {
        return RequireLogin($this, 'parent::get_reco_item_to_items', func_get_args());
    }

    function get_reco_session_to_items($ratings=null, $user_properties=null,
                                  $amt=null, $cursor=null, $filters=null, $exclude_rated_items=null)
    {
        return RequireLogin($this, 'parent::get_reco_session_to_items', func_get_args());
    }

    function get_reco_user_to_items($user_id, $amt=null, $cursor=null, $filters=null,
                               $exclude_rated_items=null)
    {
        return RequireLogin($this, 'parent::get_reco_user_to_items', func_get_args());
    }

    function create_or_update_rating($user_id, $item_id, $rating, $timestamp=null)
    {
        return RequireLogin($this, 'parent::create_or_update_rating', func_get_args());
    }

    function create_or_update_user_ratings_bulk($user_id, $ratings)
    {
        return RequireLogin($this, 'parent::create_or_update_user_ratings_bulk', func_get_args());
    }

    function create_or_update_ratings_bulk($ratings, $chunk_size=(1<<14))
    {
        return RequireLogin($this, 'parent::create_or_update_ratings_bulk', func_get_args());
    }

    function list_user_ratings($user_id, $amt=null, $page=null)
    {
        return RequireLogin($this, 'parent::list_user_ratings', func_get_args());
    }

    function list_ratings($amt=null, $cursor=null)
    {
        return RequireLogin($this, 'parent::list_ratings', func_get_args());
    }

    function delete_rating($user_id, $item_id)
    {
        return RequireLogin($this, 'parent::delete_rating', func_get_args());
    }

    function delete_user_ratings($user_id)
    {
        return RequireLogin($this, 'parent::delete_user_ratings', func_get_args());
    }

    function create_interaction($user_id, $item_id, $interaction_type, $timestamp=null)
    {
        return RequireLogin($this, 'parent::create_interaction', func_get_args());
    }

    function create_interactions_bulk($interactions, $chunk_size=(1<<14))
    {
        return RequireLogin($this, 'parent::create_interactions_bulk', func_get_args());
    }

    function get_data_dump_signed_urls($name, $content_type, $resource)
    {
        return RequireLogin($this, 'parent::get_data_dump_signed_urls', func_get_args());
    }

    function trigger_background_task($task_name, $payload=null)
    {
        return RequireLogin($this, 'parent::trigger_background_task', func_get_args());
    }

    function get_background_tasks($task_name)
    {
        return RequireLogin($this, 'parent::get_background_tasks', func_get_args());
    }

}

?>
