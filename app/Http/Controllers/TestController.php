<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function test()
    {
        return response()->json([
            'message' => 'API is working!',
            'timestamp' => now()->toDateTimeString()
        ]);
    }
    
    public function groups()
    {
        return response()->json([
            'groups' => [
                [
                    'id' => 1,
                    'name' => 'Trip to Marrakech',
                    'description' => 'Our weekend trip expenses',
                    'members' => [
                        ['id' => 1, 'name' => 'User A'],
                        ['id' => 2, 'name' => 'User B'],
                        ['id' => 3, 'name' => 'User C']
                    ]
                ],
                [
                    'id' => 2,
                    'name' => 'Office lunch',
                    'description' => 'Weekly team lunch',
                    'members' => [
                        ['id' => 1, 'name' => 'User A'],
                        ['id' => 4, 'name' => 'User D'] 
                    ]
                ]
            ]
        ]);
    }
    
    public function expenses()
    {
        return response()->json([
            'expenses' => [
                [
                    'id' => 1,
                    'description' => 'Dinner',
                    'amount' => 120,
                    'paid_by' => ['id' => 1, 'name' => 'User A'],
                    'shared_with' => [
                        ['id' => 1, 'name' => 'User A'],
                        ['id' => 2, 'name' => 'User B'],
                        ['id' => 3, 'name' => 'User C']
                    ]
                ],
                [
                    'id' => 2,
                    'description' => 'Taxi',
                    'amount' => 60,
                    'paid_by' => ['id' => 2, 'name' => 'User B'],
                    'shared_with' => [
                        ['id' => 1, 'name' => 'User A'],
                        ['id' => 2, 'name' => 'User B']
                    ]
                ]
            ]
        ]);
    }
    
    public function balances()
    {
        return response()->json([
            'balances' => [
                ['user_id' => 1, 'user_name' => 'User A', 'balance' => 80],
                ['user_id' => 2, 'user_name' => 'User B', 'balance' => 20],
                ['user_id' => 3, 'user_name' => 'User C', 'balance' => -40],
                ['user_id' => 4, 'user_name' => 'User D', 'balance' => -60]
            ],
            'simplified_debts' => [
                [
                    'from_user_id' => 3,
                    'from_user_name' => 'User C',
                    'to_user_id' => 1,
                    'to_user_name' => 'User A',
                    'amount' => 40
                ],
                [
                    'from_user_id' => 4,
                    'from_user_name' => 'User D',
                    'to_user_id' => 1,
                    'to_user_name' => 'User A',
                    'amount' => 40
                ],
                [
                    'from_user_id' => 4,
                    'from_user_name' => 'User D',
                    'to_user_id' => 2,
                    'to_user_name' => 'User B',
                    'amount' => 20
                ]
            ]
        ]);
    }
}
