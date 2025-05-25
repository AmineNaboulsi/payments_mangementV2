<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\RedisService;
use App\Models\Group;

class DebugController extends Controller
{
    public function testRedis()
    {
        try {
            $redis = app('redis.service');
            $redis->set('test_key', 'test_value', 60);
            $value = $redis->get('test_key');
            
            return response()->json([
                'status' => 'success',
                'message' => 'Redis is working',
                'test_value' => $value
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Redis error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function testAuth()
    {
        try {
            $user = Auth::user();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Auth is working',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Auth error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function testGroups()
    {
        try {
            $user = Auth::user();
            
            Log::info('Testing groups for user: ' . $user->id);
            
            // Test direct query without cache
            $groups = $user->groups()
                ->wherePivot('status', 'accepted')
                ->with('creator')
                ->get();
                
            Log::info('Groups found: ' . $groups->count());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Groups query working',
                'user_id' => $user->id,
                'groups_count' => $groups->count(),
                'groups' => $groups
            ]);
        } catch (\Exception $e) {
            Log::error('Groups test error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Groups error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    public function testExpenses(Group $group)
    {
        try {
            $user = Auth::user();
            
            Log::info('Testing expenses for group: ' . $group->id . ', user: ' . $user->id);
            
            // Check if user is member of group
            $isMember = $group->members()
                ->where('user_id', $user->id)
                ->wherePivot('status', 'accepted')
                ->exists();
                
            if (!$isMember) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User is not a member of this group'
                ], 403);
            }
            
            // Test direct query without cache or relationships
            $expenses = $group->expenses()->get();
            Log::info('Raw expenses found: ' . $expenses->count());
            
            // Test with relationships
            $expensesWithRels = $group->expenses()
                ->with('paidBy', 'shares.user')
                ->orderBy('date', 'desc')
                ->get();
            Log::info('Expenses with relationships: ' . $expensesWithRels->count());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Expenses query working',
                'group_id' => $group->id,
                'user_id' => $user->id,
                'is_member' => $isMember,
                'raw_expenses_count' => $expenses->count(),
                'expenses_with_rels_count' => $expensesWithRels->count(),
                'expenses' => $expensesWithRels
            ]);
        } catch (\Exception $e) {
            Log::error('Expenses test error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Expenses error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
