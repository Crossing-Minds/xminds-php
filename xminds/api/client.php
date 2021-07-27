<?php
/*
xminds.api.client
~~~~~~~~~~~~~~~~~

This module implements the requests for all API endpoints.
The client handles the logic to automatically get a new JWT token using the refresh token
*/

require_once ("apirequest.php");
require_once ("exceptions.php");

/*
import base64
from functools import wraps
import logging
import sys
import time
from urllib.parse import quote
from binascii import Error as BinasciiError

import numpy

from ..compat import tqdm
from .apirequest import CrossingMindsApiJsonRequest, CrossingMindsApiPythonRequest
from .exceptions import DuplicatedError, JwtTokenExpired, ServerError


def require_login(method):
    @wraps(method)
    def wrapped(self, *args, **kwargs):
        try:
            return method(self, *args, **kwargs)
        except JwtTokenExpired:
            if not $this->_refresh_token or not $this->auto_refresh_token:
                raise
            $this->login_refresh_token()
            return method(self, *args, **kwargs)
    return wrapped
*/

class CrossingMindsApiClient
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
            throw NotImplementedError('unknown serializer {serializer}');
        $this->api = new $cls($api_kwargs);
    }

    # === Account ===

    //@require_login
    function create_individual_account($first_name, $last_name, $email, $password, $role='backend')
    {
        /*
        Create a new individual account

        :param str first_name:
        :param str last_name:
        :param str email:
        :param str password:
        :returns: {'id': str}
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

    //@require_login
    function create_service_account($name, $password, $role='frontend')
    {
        /*
        Create a new service account

        :param str name:
        :param str password:
        :param str? role:
        :returns: {'id': str}
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

    //@require_login
    function list_accounts()
    {
        /*
        Get all accounts on the current organization

        :returns: {
            'individual_accounts': [
                {
                    'first_name': str,
                    'last_name': str,
                    'email': str,
                    'role': str,
                    'verified': bool,
                },
            ],
            'service_accounts': [
                {
                    'name': str,
                    'role': str,
                },
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
        :returns: {
            'token': str,
            'database': {
                'id': str,
                'name': str,
                'description': str,
                'item_id_type': str,
                'user_id_type': str,
            },
        }
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
        :returns: {
            'token': str,
            'database': {
                'id': str,
                'name': str,
                'description': str,
                'item_id_type': str,
                'user_id_type': str,
            },
        }
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
        :returns: {
            'token': str,
        }
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
        :returns: {
            'token': str,
            'database': {
                'id': str,
                'name': str,
                'description': str,
                'item_id_type': str,
                'user_id_type': str,
            },
        }
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

    //@require_login
    function get_organization()
    {
        /*
        get organization meta-data
        */
        $path = 'organizations/current/';
        return $this->api->get($path);
    }

    //@require_login
    function create_or_partial_update_organization($metadata, $preserve=null)
    {
        /*
        create, or apply deep partial update of meta-data
        :param dict metadata: meta-data to store structured as unvalidated JSON-compatible dict
        :param bool? preserve: set to `True` to append values instead of replace as in RFC7396
        */
        $path = 'organizations/current/';
        $data = ['metadata'=> $metadata];
        if ($preserve != null)
            $data['preserve'] = $preserve;
        return $this->api->patch($path, $data);
    }

    //@require_login
    function partial_update_organization_preview($metadata, $preserve=null)
    {
        /*
        preview deep partial update of extra data, without changing any state
        :param dict metadata: extra meta-data to store structured as unvalidated JSON-compatible dict
        :param bool? preserve: set to `True` to append values instead of replace as in RFC7396
        :returns: {
            'metadata_old': {
                'description': str,
                'extra': {**any-key: any-val},
            },
            'metadata_new': {
                'description': str,
                'extra': {**any-key: any-val},
            },
        }
        */
        $path = 'organizations/current/preview/';
        $data = ['metadata'=> $metadata];
        if ($preserve != null)
            $data['preserve'] = $preserve;
        return $this->api->patch($path, $data);
    }

    # === Database ===

    //@require_login
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

    //@require_login
    function get_all_databases($amt=null, $page=null)
    {
        /*
        Get all databases on the current organization

        :param int? amt: amount of databases by page (default: API default)
        :param int? page: page number (default: 1)
        :returns: {
            'has_next': bool,
            'next_page': int,
            'databases': [
                {
                    'id': int,
                    'name': str,
                    'description': str,
                    'item_id_type': str,
                    'user_id_type': str
                },
            ]
        }
        */
        $path = 'databases/';
        $params = [];
        if ($amt)
            $params['amt'] = amt;
        if ($page)
            $params['page'] = page;
        return $this->api->get($path, $params);
    }

    //@require_login
    function get_database()
    {
        /*
        Get details on current database

        :returns: {
            'id': str,
            'name': str,
            'description': str,
            'item_id_type': str,
            'user_id_type': str,
            'counters': {
                'rating': int,
                'user': int,
                'item': int,
            },
            'metadata': {**any-key: any-val},
        }
        */
        $path = 'databases/current/';
        return $this->api->get($path);
    }

    //@require_login
    function partial_update_database($description=null, $metadata=null, $preserve=null)
    {
        /*
        update description, and apply deep partial update of extra meta-data
        :param str? description: description of DB
        :param dict? metadata: extra data to store structured as unvalidated JSON-compatible dict
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

    //@require_login
    function partial_update_database_preview($description=null, $metadata=null, $preserve=null)
    {
        /*
        preview deep partial update of extra data, without changing any state
        :param str? description: description of DB
        :param dict? metadata: extra data to store structured as unvalidated JSON-compatible dict
        :param bool? preserve: set to `True` to append values instead of replace as in RFC7396
        :returns: {
            'metadata_old': {
                'description': str,
                'metadata': {**any-key: any-val},
            },
            'metadata_new': {
                'description': str,
                'metadata': {**any-key: any-val},
            },
        }
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

    //@require_login
    function delete_database()
    {
        /*
        Delete current database.
        */
        $path = 'databases/current/';
        return $this->api->delete($path, [], ['timeout'=>29]);
    }

    //@require_login
    function status()
    {
        /*
        Get readiness status of current database.
        */
        $path = 'databases/current/status/';
        return $this->api->get($path);
    }

    # === User Property ===

    //@require_login
    function get_user_property($property_name)
    {
        /*
        Get one user-property.

        :param str property_name: property name
        :returns: {
            'property_name': str,
            'value_type': str,
            'repeated': bool,
        }
        */
        $path = 'users-properties/'.$this->escape_url($property_name).'/';
        return $this->api->get($path);
    }

    //@require_login
    function list_user_properties()
    {
        /*
        Get all user-properties for the current database.

        :returns: {
            'properties': [{
                'property_name': str,
                'value_type': str,
                'repeated': bool,
            }],
        }
        */
        $path = 'users-properties/';
        return $this->api->get($path);
    }

    //@require_login
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

    //@require_login
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

    //@require_login
    function get_user($user_id)
    {
        /*
        Get one user given its ID.

        :param ID user_id: user ID
        :returns: {
            'item': {
                'id': ID,
                *<property_name: property_value>,
            }
        }
        */
        $user_id = $this->_userid2url($user_id);
        $path = "users/{$user_id}/";
        $resp = $this->api->get($path);
        $resp['user']['user_id'] = $this->_body2userid($resp['user']['user_id']);
        return $resp;
    }

    //@require_login
    function list_users_paginated($amt=null, $cursor=null)
    {
        /*
        Get multiple users by page.
        The response is paginated, you can control the response amount and offset
        using the query parameters ``amt`` and ``cursor``.

        :param int? amt: amount to return (default: use the API default)
        :param str? cursor: Pagination cursor
        :returns: {
            'users': array with fields ['id': ID, *<property_name: value_type>]
                contains only the non-repeated values,
            'users_m2m': dict of arrays for repeated values:
                {
                    *<repeated_property_name: {
                        'name': str,
                        'array': array with fields ['user_index': uint32, 'value_id': value_type],
                    }>
                },
            'has_next': bool,
            'next_cursor': str, pagination cursor to use in next request to get more users,
        }
        */
        $path = 'users-bulk/';
        $params = [];
        if ($amt)
            $params['amt'] = $amt;
        if ($cursor)
            $params['cursor'] = $cursor;
        $resp = $this->api.get($path, $params);
        $resp['users'] = $this->_body2userid($resp['users']);
        return $resp;
    }

    //require_login
    function create_or_update_user($user)
    {
        /*
        Create a new user, or update it if the ID already exists.

        :param dict user: user ID and properties {'user_id': ID, *<property_name: property_value>}
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

    //@require_login
    function create_or_update_users_bulk($users, $users_m2m=null, $chunk_size=(1<<10))
    {
        /*
        Create many users in bulk, or update the ones for which the id already exist.

        :param array users: array with fields ['id': ID, *<property_name: value_type>]
            contains only the non-repeated values,
        :param dict? users_m2m: dict of arrays for repeated values:
            {
                *<repeated_property_name: {
                    'name': str,
                    'array': array with fields ['user_index': uint32, 'value_id': value_type],
                }>
            }
        :param int? chunk_size: split the requests in chunks of this size (default: 1K)
        */
        $path = 'users-bulk/';
        $chunked = $this->_chunk_users($users, $users_m2m, $chunk_size);
        foreach ($chunked as list($users_chunk, $users_m2m_chunk))
        {
            $data = [
                'users'=> $users_chunk,
                'users_m2m'=> $users_m2m_chunk
            ];
            $this->api->put($path, $data, ['timeout'=>60]);
        }
    }

    //@require_login
    function partial_update_user($user, $create_if_missing=null)
    {
        /*
        Partially update some properties of an user

        :param dict user: user ID and properties {'user_id': ID, *<property_name: property_value>}
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

    //@require_login
    function partial_update_users_bulk($users, $users_m2m=null, $create_if_missing=null,
                                  $chunk_size=(1 << 10))
    {
        /*
        Partially update some properties of many users.

        :param array users: array with fields ['id': ID, *<property_name: value_type>]
            contains only the non-repeated values,
        :param dict? users_m2m: dict of arrays for repeated values:
            {
                *<repeated_property_name: {
                    'name': str,
                    'array': array with fields ['user_index': uint32, 'value_id': value_type],
                }>
            }
        :param bool? create_if_missing: to control whether an error should be returned or new users
        should be created when the ``user_id`` does not already exist. (default: False)
        :param int? chunk_size: split the requests in chunks of this size (default: 1K)
        */
        $path = 'users-bulk/';
        $data = [];
        if ($create_if_missing != null)
            $data['create_if_missing'] = $create_if_missing;
        $chunked = $this->_chunk_users($users, $users_m2m, $chunk_size);
        foreach ($chunked as list($users_chunk, $users_m2m_chunk))
        {
            $data['users'] = $users_chunk;
            $data['users_m2m'] = $users_m2m_chunk;
            $this->api->patch($path, $data, ['timeout'=>60]);
        }
    }

    function _chunk_users($users, $users_m2m, $chunk_size)
    {
        $users_m2m = $users_m2m ?? [];
        # cast dict to list of dict
        if (is_array($users_m2m))
        {
            $users_m2m_b = [];
            foreach ($users_m2m as $m2m) //list($name, $array)
            {
                array_push($users_m2m_b, ['name'=> $m2m['name'], 'array'=> $m2m['array']]);
            }
            $users_m2m = $users_m2m_b;
        }
        $n_chunks = (int)(ceil(count($users) / $chunk_size));
        $out = [];
        for ($i = 0; $i < $n_chunks; $i++)
        {
            $start_idx = $i * $chunk_size;
            $end_idx = ($i + 1) * $chunk_size;
            $users_chunk = array_slice($users, $start_idx, $chunk_size);
            # split M2M array-optimized if any
            $users_m2m_chunk = [];
            foreach ($users_m2m as $m2m)
            {
                $array = $m2m['array'];
                //if isinstance(array, numpy.ndarray):
                //    mask = (array['user_index'] >= start_idx) & (array['user_index'] < end_idx)
                //    array_chunk = array[mask]  # does copy
                //    array_chunk['user_index'] -= start_idx
                //else:

                //print('array-optimized many-to-many format is not efficient '
                //                .'with JSON. Use numpy arrays and pkl serializer instead');
                $array_chunk = [];
                foreach($array as $row)
                {
                    if ($start_idx <= $row['user_index'] and $row['user_index'] < $end_idx)
                        array_push($array_chunk, ['user_index'=> $row['user_index'] - $start_idx, 'value_id'=> $row['value_id']]);
                }
                array_push($users_m2m_chunk, ['name'=> $m2m['name'], 'array'=> $array_chunk]);
            }
            array_push($out, [$this->_userid2body($users_chunk), $users_m2m_chunk]);
        }
        return $out;
    }

    # === Item Property ===

    //@require_login
    function get_item_property($property_name)
    {
        /*
        Get one item-property.

        :param str property_name: property name
        :returns: {
            'property_name': str,
            'value_type': str,
            'repeated': bool,
        }
        */
        $path = "items-properties/{$this->escape_url($property_name)}/";
        return $this->api->get($path);
    }

    //@require_login
    function list_item_properties()
    {
        /*
        Get all item-properties for the current database.

        :returns: {
            'properties': [{
                'property_name': str,
                'value_type': str,
                'repeated': bool,
            }],
        }
        */
        $path = 'items-properties/';
        return $this->api->get($path);
    }

    //@require_login
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

    //@require_login
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

    //@require_login
    function get_item($item_id)
	{
        /*
        Get one item given its ID.

        :param ID item_id: item ID
        :returns: {
            'item': {
                'id': ID,
                *<property_name: property_value>,
            }
        }
        */
        $item_id = $this->_itemid2url($item_id);
        $path = "items/{$item_id}/";
        $resp = $this->api->get($path);
        $resp['item']['item_id'] = $this->_body2itemid($resp['item']['item_id']);
        return $resp;
	}
/*
    @require_login
    def list_items(self, items_id):
        """
        Get multiple items given their IDs.
        The items in the response are not aligned with the input.
        In particular this endpoint does not raise NotFoundError if any item in missing.
        Instead, the missing items are simply not present in the response.

        :param ID-array items_id: items IDs
        :returns: {
            'items': array with fields ['id': ID, *<property_name: value_type>]
                contains only the non-repeated values,
            'items_m2m': dict of arrays for repeated values:
                {
                    *<repeated_property_name: {
                        'name': str,
                        'array': array with fields ['item_index': uint32, 'value_id': value_type],
                    }>
                }
        }
        """
        items_id = $this->_itemid2body(items_id)
        path = f'items-bulk/list/'
        data = {'items_id': items_id}
        resp = $this->api.post(path=path, data=data)
        resp['items'] = $this->_body2itemid(resp['items'])
        return resp

    @require_login
    def list_items_paginated(self, amt=null, cursor=null):
        """
        Get multiple items by page.
        The response is paginated, you can control the response amount and offset
        using the query parameters ``amt`` and ``cursor``.

        :param int? amt: amount to return (default: use the API default)
        :param str? cursor: Pagination cursor
        :returns: {
            'items': array with fields ['id': ID, *<property_name: value_type>]
                contains only the non-repeated values,
            'items_m2m': dict of arrays for repeated values:
                {
                    *<repeated_property_name: {
                        'name': str,
                        'array': array with fields ['item_index': uint32, 'value_id': value_type],
                    }>
                },
            'has_next': bool,
            'next_cursor': str, pagination cursor to use in next request to get more items,
        }
        """
        path = f'items-bulk/'
        params = {}
        if amt:
            params['amt'] = amt
        if cursor:
            params['cursor'] = cursor
        resp = $this->api.get(path=path, params=params)
        resp['items'] = $this->_body2itemid(resp['items'])
        return resp
*/
    //@require_login
    function create_or_update_item($item)
    {
        /*
        Create a new item, or update it if the ID already exists.

        :param dict item: item ID and properties {'item_id': ID, *<property_name: property_value>}
        */
        $item = (array)($item);
        $item_id = $this->_itemid2url($item['item_id']);
		unset($user['item_id']);
        $path = "items/{$item_id}/";
        $data = [
            'item'=> $item,
        ];
        return $this->api->put($path, $data);
    }
/*
    @require_login
    def create_or_update_items_bulk(self, items, items_m2m=null, chunk_size=(1<<10)):
        """
        Create many items in bulk, or update the ones for which the id already exist.

        :param array items: array with fields ['id': ID, *<property_name: value_type>]
            contains only the non-repeated values,
        :param array? items_m2m: dict of arrays for repeated values:
            {
                *<repeated_property_name: {
                    'name': str,
                    'array': array with fields ['item_index': uint32, 'value_id': value_type],
                }>
            }
        :param int? chunk_size: split the requests in chunks of this size (default: 1K)
        """
        path = f'items-bulk/'
        for items_chunk, items_m2m_chunk in $this->_chunk_items(items, items_m2m, chunk_size):
            data = {
                'items': items_chunk,
                'items_m2m': items_m2m_chunk,
            }
            $this->api.put(path=path, data=data, timeout=60)

    @require_login
    def partial_update_item(self, item, create_if_missing=null):
        """
        Partially update some properties of an item.

        :param dict item: item ID and properties {'item_id': ID, *<property_name: property_value>}
        :param bool? create_if_missing: control whether an error should be returned or a new item
        should be created when the ``item_id`` does not already exist. (default: false)
        """
        item = dict(item)
        item_id = $this->_itemid2url(item.pop('item_id'))
        path = f'items/{item_id}/'
        data = {
            'item': item,
        }
        if create_if_missing is not null:
            data['create_if_missing'] = create_if_missing
        return $this->api.patch(path=path, data=data)

    @require_login
    def partial_update_items_bulk(self, items, items_m2m=null, create_if_missing=null,
                                  chunk_size=(1 << 10)):
        """
        Partially update some properties of many items.

        :param array items: array with fields ['id': ID, *<property_name: value_type>]
            contains only the non-repeated values,
        :param array? items_m2m: dict of arrays for repeated values:
            {
                *<repeated_property_name: {
                    'name': str,
                    'array': array with fields ['item_index': uint32, 'value_id': value_type],
                }>
            }
        :param bool? create_if_missing: control whether an error should be returned or a new item
        should be created when the ``item_id`` does not already exist. (default: false)
        :param int? chunk_size: split the requests in chunks of this size (default: 1K)
        """
        path = f'items-bulk/'
        data = {}
        if create_if_missing is not null:
            data['create_if_missing'] = create_if_missing
        for items_chunk, items_m2m_chunk in $this->_chunk_items(items, items_m2m, chunk_size):
            data['items'] = items_chunk
            data['items_m2m'] = items_m2m_chunk
            $this->api.patch(path=path, data=data, timeout=60)

    def _chunk_items(self, items, items_m2m, chunk_size):
        items_m2m = items_m2m or []
        # cast dict to list of dict
        if isinstance(items_m2m, dict):
            items_m2m = [{'name': name, 'array': array}
                         for name, array in items_m2m.items()]
        n_chunks = int(numpy.ceil(len(items) / chunk_size))
        for i in tqdm(range(n_chunks), disable=(True if n_chunks < 4 else null)):
            start_idx = i * chunk_size
            end_idx = (i + 1) * chunk_size
            items_chunk = items[start_idx:end_idx]
            # split M2M array-optimized if any
            items_m2m_chunk = []
            for m2m in items_m2m:
                array = m2m['array']
                if isinstance(array, numpy.ndarray):
                    mask = (array['item_index'] >= start_idx) & (array['item_index'] < end_idx)
                    array_chunk = array[mask]  # does copy
                    array_chunk['item_index'] -= start_idx
                else:
                    logging.warning('array-optimized many-to-many format is not efficient '
                                    'with JSON. Use numpy arrays and pkl serializer instead')
                    array_chunk = [
                        {'item_index': row['item_index'] - start_idx, 'value_id': row['value_id']}
                        for row in array
                        if start_idx <= row['item_index'] < end_idx
                    ]
                items_m2m_chunk.append({'name': m2m['name'], 'array': array_chunk})
            yield $this->_itemid2body(items_chunk), items_m2m_chunk

    # === Reco: Item-to-item ===

    @require_login
    def get_reco_item_to_items(self, item_id, amt=null, cursor=null, filters=null):
        """
        Get similar items.

        :param ID item_id: item ID
        :param int? amt: amount to return (default: use the API default)
        :param str? cursor: Pagination cursor
        :param list-str? filters: Filter by properties. Filter format: ['<PROP_NAME>:<OPERATOR>:<OPTIONAL_VALUE>',...]
        :returns: {
            'items_id': array of items IDs,
            'next_cursor': str, pagination cursor to use in next request to get more items,
        }
        """
        item_id = $this->_itemid2url(item_id)
        path = f'recommendation/items/{item_id}/items/'
        params = {}
        if amt:
            params['amt'] = amt
        if cursor:
            params['cursor'] = cursor
        if filters:
            params['filters'] = filters
        resp = $this->api.get(path=path, params=params)
        resp['items_id'] = $this->_body2itemid(resp['items_id'])
        return resp

    # === Reco: Session-to-item ===

    @require_login
    def get_reco_session_to_items(self, ratings=null, user_properties=null,
                                  amt=null, cursor=null, filters=null, exclude_rated_items=null):
        """
        Get items recommendations given the ratings of an anonymous session.

        :param array? ratings: ratings array with fields ['item_id': ID, 'rating': float]
        :param dict? user_properties: user properties {**property_name: property_value(s)}
        :param int? amt: amount to return (default: use the API default)
        :param str? cursor: Pagination cursor
        :param list-str? filters: Filter by properties. Filter format: ['<PROP_NAME>:<OPERATOR>:<OPTIONAL_VALUE>',...]
        :param bool? exclude_rated_items: exclude rated items from response
        :returns: {
            'items_id': array of items IDs,
            'next_cursor': str, pagination cursor to use in next request to get more items,
        }
        """
        path = f'recommendation/sessions/items/'
        data = {}
        if ratings is not null:
            data['ratings'] = $this->_itemid2body(ratings)
        if user_properties:
            data['user_properties'] = user_properties
        if amt:
            data['amt'] = amt
        if cursor:
            data['cursor'] = cursor
        if filters:
            data['filters'] = filters
        if exclude_rated_items is not null:
            data['exclude_rated_items'] = exclude_rated_items
        resp = $this->api.post(path=path, data=data)
        resp['items_id'] = $this->_body2itemid(resp['items_id'])
        return resp

    # === Reco: User-to-item ===

    @require_login
    def get_reco_user_to_items(self, user_id, amt=null, cursor=null, filters=null,
                               exclude_rated_items=null):
        """
        Get items recommendations given a user ID.

        :param ID user_id: user ID
        :param int? amt: amount to return (default: use the API default)
        :param str? cursor: Pagination cursor
        :param list-str? filters: Filter by properties. Filter format: ['<PROP_NAME>:<OPERATOR>:<OPTIONAL_VALUE>',...]
        :param bool? exclude_rated_items: exclude rated items from response
        :returns: {
            'items_id': array of items IDs,
            'next_cursor': str, pagination cursor to use in next request to get more items,
        }
        """
        user_id = $this->_userid2url(user_id)
        path = f'recommendation/users/{user_id}/items/'
        params = {}
        if amt:
            params['amt'] = amt
        if cursor:
            params['cursor'] = cursor
        if filters:
            params['filters'] = filters
        if exclude_rated_items is not null:
            params['exclude_rated_items'] = exclude_rated_items
        resp = $this->api.get(path=path, params=params)
        resp['items_id'] = $this->_body2itemid(resp['items_id'])
        return resp

    # === User Ratings ===

    @require_login
    def create_or_update_rating(self, user_id, item_id, rating, timestamp=null):
        """
        Create or update a rating for a user and an item.
        If the rating exists for the tuple (user_id, item_id) then it is updated,
        otherwise it is created.

        :param ID user_id: user ID
        :param ID item_id: item ID
        :param float rating: rating value
        :param float? timestamp: rating timestamp (default: now)
        """
        user_id = $this->_userid2url(user_id)
        item_id = $this->_itemid2url(item_id)
        path = f'users/{user_id}/ratings/{item_id}/'
        data = {
            'rating': rating,
        }
        if timestamp is not null:
            data['timestamp'] = timestamp
        return $this->api.put(path=path, data=data)

    @require_login
    def create_or_update_user_ratings_bulk(self, user_id, ratings):
        """
        Create or update bulks of ratings for a single user and many items.

        :param ID user_id: user ID
        :param array ratings: ratings array with fields:
            ['item_id': ID, 'rating': float, 'timestamp': float]
        """
        user_id = $this->_userid2url(user_id)
        path = f'users/{user_id}/ratings/'
        data = {
            'ratings': $this->_itemid2body(ratings),
        }
        return $this->api.put(path=path, data=data, timeout=10)

    @require_login
    def create_or_update_ratings_bulk(self, ratings, chunk_size=(1<<14)):
        """
        Create or update large bulks of ratings for many users and many items.

        :param array ratings: ratings array with fields:
            ['user_id': ID, 'item_id': ID, 'rating': float, 'timestamp': float]
        :param int? chunk_size: split the requests in chunks of this size (default: 16K)
        """
        path = f'ratings-bulk/'
        n_chunks = int(numpy.ceil(len(ratings) / chunk_size))
        for i in tqdm(range(n_chunks), disable=(True if n_chunks < 4 else null)):
            ratings_chunk = ratings[i*chunk_size:(i+1)*chunk_size]
            ratings_chunk = $this->_userid2body($this->_itemid2body(ratings_chunk))
            data = {
                'ratings': ratings_chunk,
            }
            $this->api.put(path=path, data=data, timeout=60)
        return

    @require_login
    def list_user_ratings(self, user_id, amt=null, page=null):
        """
        List the ratings of one user (paginated)

        :param ID user_id: user ID
        :param int? amt: amount of ratings by page (default: API default)
        :param int? page: page number (default: 1)
        :returns: {
            'has_next': bool,
            'next_page': int,
            'user_ratings': ratings array with fields
                ['item_id': ID, 'rating': float, 'timestamp': float]
        }
        """
        user_id = $this->_userid2url(user_id)
        path = f'users/{user_id}/ratings/'
        params = {}
        if amt:
            params['amt'] = amt
        if page:
            params['page'] = page
        resp = $this->api.get(path=path, params=params)
        resp['user_ratings'] = $this->_body2itemid(resp['user_ratings'])
        return resp

    @require_login
    def list_ratings(self, amt=null, cursor=null):
        """
        List the ratings of one database

        :param int? amt: amount to return (default: use the API default)
        :param str? cursor: Pagination cursor
        :returns: {
            'has_next': bool,
            'next_cursor': str,
            'ratings': array with fields
                ['item_id': ID, 'user_id': ID, 'rating': float, 'timestamp': float]
        }
        """
        path = f'ratings-bulk/'
        params = {}
        if amt:
            params['amt'] = amt
        if cursor:
            params['cursor'] = cursor
        resp = $this->api.get(path=path, params=params)
        resp['ratings'] = $this->_body2userid($this->_body2itemid(resp['ratings']))
        return resp

    @require_login
    def delete_rating(self, user_id, item_id):
        """
        Delete a single rating for a given user.

        :param ID user_id: user ID
        :param ID item_id: item ID
        """
        user_id = $this->_userid2url(user_id)
        item_id = $this->_itemid2url(item_id)
        path = f'users/{user_id}/ratings/{item_id}'
        return $this->api.delete(path=path)

    @require_login
    def delete_user_ratings(self, user_id):
        """
        Delete all ratings of a given user.

        :param ID user_id: user ID
        """
        user_id = $this->_userid2url(user_id)
        path = f'users/{user_id}/ratings/'
        return $this->api.delete(path=path)

    # === User Interactions ===

    @require_login
    def create_interaction(self, user_id, item_id, interaction_type, timestamp=null):
        """
        This endpoint allows you to create a new interaction for a user and an item.
        An inferred rating will be created or updated for the tuple (user_id, item_id).
        The taste profile of the user will then be updated in real-time by the online machine learning algorithm.

        :param ID user_id: user ID
        :param ID item_id: item ID
        :param str interaction_type: Interaction type
        :param float? timestamp: rating timestamp (default: now)
        """
        user_id = $this->_userid2url(user_id)
        item_id = $this->_itemid2url(item_id)
        path = f'users/{user_id}/interactions/{item_id}/'
        data = {
            'interaction_type': interaction_type,
        }
        if timestamp is not null:
            data['timestamp'] = timestamp
        return $this->api.post(path=path, data=data)

    @require_login
    def create_interactions_bulk(self, interactions, chunk_size=(1<<14)):
        """
        Create or update large bulks of interactions for many users and many items.
        Inferred ratings will be created or updated for all tuples (user_id, item_id).

        :param array interactions: interactions array with fields:
            ['user_id': ID, 'item_id': ID, 'interaction_type': str, 'timestamp': float]
        :param int? chunk_size: split the requests in chunks of this size (default: 16K)
        """
        path = f'interactions-bulk/'
        n_chunks = int(numpy.ceil(len(interactions) / chunk_size))
        for i in tqdm(range(n_chunks), disable=(True if n_chunks < 4 else null)):
            interactions_chunk = interactions[i*chunk_size:(i+1)*chunk_size]
            interactions_chunk = $this->_userid2body($this->_itemid2body(interactions_chunk))
            data = {
                'interactions': interactions_chunk,
            }
            $this->api.post(path=path, data=data, timeout=10)
        return

    # === Data Dump Storage ===

    @require_login
    def get_data_dump_signed_urls(self, name, content_type, resource):
        """
        Get signed url to upload a file. (url_upload and url_report)

        :param str? name: filename
        :param str? content_type:
        :param str? resource: values allowed are `items`, `users`, `ratings` and `ratings_implicit`.
        :returns: {
            'url_upload': str,
            'url_report': str,
        }
        """
        path = f'data-dump-storage/signed-url/'
        params = {'name': name,
                  'content_type': content_type,
                  'resource': resource}
        return $this->api.get(path=path, params=params)

    # === Scheduled Background Tasks ===

    @require_login
    def trigger_background_task(self, task_name, payload=null):
        """
        Trigger background task such as retraining of ML models.
        You should not have to call this endpoint yourself, as this is done automatically.

        :param str task_name: for instance ``'ml_model_retrain'``
        :param dict? payload: optional task payload
        :returns: {
            'task_id': str,
        }
        :raises: DuplicatedError with error name 'TASK_ALREADY_RUNNING'
            if this task is already running
        """
        path = f'tasks/{$this->escape_url(task_name)}/'
        data = {}
        if payload:
            data['payload'] = payload
        return $this->api.post(path=path, data=data)

    @require_login
    def get_background_tasks(self, task_name):
        """
        List currently running background tasks such as ML models training.

        :param str task_name: for instance ``'ml_model_retrain'``
        :returns: {
            'tasks': [{
                'name': string, Task name
                'start_time': int, Start timestamp
                'details': dict, Execution details, like progress message
            }],
        }
        """
        path = f'tasks/{$this->escape_url(task_name)}/recents/'
        return $this->api.get(path=path)

    def wait_until_ready(self, timeout=600, sleep=1):
        """
        Wait until the current database status is ready, meaning at least one model has been trained

        :param int? timeout: maximum time to wait, raise RuntimeError if exceeded (default: 10min)
        :param int? sleep: time to wait between polling (default: 1s)
        """
        assert sleep > 0.1
        resp = null
        time_start = time.time()
        while time.time() - time_start < timeout:
            time.sleep(sleep)
            resp = $this->status()
            if resp['status'] == 'ready':
                return
        raise RuntimeError(f'API not ready before {timeout}s. Last response: {resp}')

    @require_login
    def trigger_and_wait_background_task(self, task_name, timeout=600, lock_wait_timeout=null,
                                         sleep=1, verbose=null):
        """
        Trigger background task such as retraining of ML models.
        You don't necessarily have to call this endpoint yourself,
        model training is also triggered automatically.
        By default this waits for an already running task before triggering the new one

        :param str task_name: for instance ``'ml_model_retrain'``
        :param int? timeout: maximum time to wait after the new task is triggered (default: 10min)
        :param int? lock_wait_timeout: if another task is already running, maximum time to wait
            for it to finish before triggering the new task (default: ``timeout``)
        :param int? sleep: time to wait between polling (default: 1s)
        :returns: {
            'task_id': str,
        }
        :raises: RuntimeError if either ``timeout`` or ``lock_wait_timeout`` is reached
        """
        assert sleep > 0.1
        if lock_wait_timeout is null:
            lock_wait_timeout = timeout
        if verbose is null:
            verbose = sys.stdout.isatty()
        # wait for already running task (if any)
        if lock_wait_timeout > 0:
            msg = 'waiting for already running...' if verbose else null
            $this->wait_for_background_task(
                task_name, lock_wait_timeout, sleep, msg=msg, wait_if_no_task=False,
                filtr=lambda t: t['status'] == 'RUNNING')
        # trigger
        try:
            task_id = $this->trigger_background_task(task_name)['task_id']
        except DuplicatedError as exc:
            if getattr(exc, 'data', {})['name'] != 'TASK_ALREADY_RUNNING':
                raise
            # edge case: something else triggered the same task at the same time
            tasks = $this->get_background_tasks(task_name)['tasks']
            task_id = next(t['task_id'] for t in tasks if t['status'] != 'COMPLETED')
        # wait for new task
        msg = 'waiting...' if verbose else null
        $this->wait_for_background_task(
            task_name, timeout, sleep, msg=msg, filtr=lambda t: t['task_id'] == task_id)

    def wait_for_background_task(self, task_name, timeout=600, sleep=1, msg=null, filtr=null,
                                 wait_if_no_task=True):
        """
        Wait for a certain background task. Optionally specified with ``filtr`` function

        :param str task_name: for instance ``'ml_model_retrain'``
        :param int? timeout: maximum time to wait after the new task is triggered (default: 10min)
        :param int? sleep: time to wait between polling (default: 1s)
        :param str? msg: either ``null`` to disable print, or message prefix (default: null)
        :param func? filtr: filter function(task: bool)
        :param bool? wait_if_no_task: wait (instead of return) if there is no task satisfying filter
        :returns: True is a task satisfying filters successfully ran, False otherwise
        :raises: RuntimeError if ``timeout`` is reached or if the task failed
        """
        spinner = '|/-\\'
        task = null
        time_start = time.time()
        time_waited = 0
        i = 0
        while time_waited < timeout:
            time.sleep(sleep)
            time_waited = time.time() - time_start
            print_time = f'{int(time_waited) // 60:d}m{int(time_waited) % 60:02d}s'
            tasks = $this->get_background_tasks(task_name)['tasks']
            try:
                task = max(filter(filtr, tasks), key=lambda t: t['start_time'])
            except ValueError:
                if wait_if_no_task:
                    continue
                else:
                    return (task is not null)
            progress = task.get('progress', '')
            if task['status'] == 'COMPLETED':
                if msg is not null:
                    print(f'\r{msg} {print_time} done   {progress:80s}')
                return True
            elif task['status'] == 'FAILED':
                raise RuntimeError(f'task {task_name} failed with: {progress}')
            if msg is not null:
                print(f'\r{msg} {print_time} {spinner[i%len(spinner)]} {progress:80s}', end='')
                sys.stdout.flush()
            i += 1
        raise RuntimeError(f'task {task_name} not done before {timeout}s. Last response: {task}')
*/
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

/*
    def _get_latest_task_progress_message(self, task_name,
                                          default=null, default_running=null, default_failed=null):
        tasks = $this->get_background_tasks(task_name)['tasks']
        if not tasks:
            return default
        latest_task = max(tasks, key=lambda t: t['start_time'])
        if latest_task['status'] == 'RUNNING':
            return latest_task.get('progress', default_running)
        if latest_task['status'] == 'FAILED':
            raise ServerError({'error': latest_task.get('error', default_failed)})
        return latest_task['status']

*/

}

?>
