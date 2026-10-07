<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'trn' => 'nullable|string|max:100',
            'address_line1' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'parish' => 'nullable|string|max:100',
            'current_password' => 'nullable|required_with:password|string',
            'password' => 'nullable|string|min:8|confirmed',
            'tenant_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_profile_photo' => 'nullable|boolean',
        ]);

        if (! empty($data['password'])) {
            if (! Hash::check($data['current_password'] ?? '', $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Current password is incorrect.',
                ]);
            }
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        unset($data['current_password'], $data['password_confirmation'], $data['tenant_logo'], $data['profile_photo'], $data['remove_profile_photo']);
        $user->update($data);

        if ($request->hasFile('profile_photo')) {
            $directory = public_path('uploads/user-photos');
            File::ensureDirectoryExists($directory);

            $file = $request->file('profile_photo');
            $filename = 'user-'.$user->id.'-'.time().'.'.$file->getClientOriginalExtension();
            $file->move($directory, $filename);

            $this->deleteUserPhoto($user->image);
            $user->update(['image' => 'uploads/user-photos/'.$filename]);
        } elseif ($request->boolean('remove_profile_photo')) {
            $this->deleteUserPhoto($user->image);
            $user->update(['image' => null]);
        }

        if ($request->hasFile('tenant_logo') && $user->tenant && $user->user_type === 'tenant_admin') {
            $directory = public_path('uploads/tenant-logos');
            File::ensureDirectoryExists($directory);

            $file = $request->file('tenant_logo');
            $filename = Str::slug($user->tenant->slug ?: $user->tenant->company_name).'-'.time().'.'.$file->getClientOriginalExtension();
            $file->move($directory, $filename);

            $user->tenant->update([
                'logo' => 'uploads/tenant-logos/'.$filename,
            ]);
        }

        return back()->with('success', 'Profile updated.');
    }

    private function deleteUserPhoto(?string $path): void
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        $path = ltrim($path, '/\\');
        if (! Str::startsWith($path, 'uploads/user-photos/')) {
            return;
        }

        $fullPath = public_path($path);
        if (File::isFile($fullPath)) {
            File::delete($fullPath);
        }
    }
}
