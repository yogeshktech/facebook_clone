<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\Like;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $users = collect([
            ['name' => 'Demo User', 'email' => 'demo@newbook.test', 'phone' => '9876543210', 'bio' => 'Main demo account - password: password'],
            ['name' => 'Yogesh Kumar', 'email' => 'yogesh@newbook.test', 'phone' => '9876543211', 'bio' => 'Laravel Developer from India'],
            ['name' => 'Priya Sharma', 'email' => 'priya@newbook.test', 'phone' => '9876543212', 'bio' => 'Love traveling and photography'],
            ['name' => 'Rahul Verma', 'email' => 'rahul@newbook.test', 'phone' => '9876543213', 'bio' => 'Cricket fan | Mumbai'],
            ['name' => 'Anita Singh', 'email' => 'anita@newbook.test', 'phone' => '9876543214', 'bio' => 'Food blogger'],
            ['name' => 'Vikram Patel', 'email' => 'vikram@newbook.test', 'phone' => '9876543215', 'bio' => 'Startup founder'],
            ['name' => 'Sneha Reddy', 'email' => 'sneha@newbook.test', 'phone' => '9876543216', 'bio' => 'Software engineer at Tech Corp'],
            ['name' => 'Amit Gupta', 'email' => 'amit@newbook.test', 'phone' => '9876543217', 'bio' => 'Music lover'],
            ['name' => 'Kavita Joshi', 'email' => 'kavita@newbook.test', 'phone' => '9876543218', 'bio' => 'Teacher | Delhi'],
            ['name' => 'Rohan Mehta', 'email' => 'rohan@newbook.test', 'phone' => '9876543219', 'bio' => 'Gamer and streamer'],
        ])->map(function ($data) {
            return User::create([
                'name' => $data['name'],
                'username' => Str::slug($data['name']).'-'.Str::random(3),
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make('password'),
                'bio' => $data['bio'],
                'location' => fake()->city(),
                'email_verified_at' => now(),
            ]);
        });

        $demo = $users->first();

        // Friendships
        foreach ($users->skip(1)->take(6) as $friend) {
            Friendship::create(['user_id' => $demo->id, 'friend_id' => $friend->id, 'status' => 'accepted']);
        }
        Friendship::create(['user_id' => $users[1]->id, 'friend_id' => $users[2]->id, 'status' => 'accepted']);
        Friendship::create(['user_id' => $users[3]->id, 'friend_id' => $users[4]->id, 'status' => 'pending']);

        // Follows
        $demo->following()->attach($users[7]->id);
        $demo->following()->attach($users[8]->id);

        // Posts
        $postTexts = [
            'Hello Newbook! Excited to be here.',
            'Beautiful sunset today!',
            'Just finished a Laravel project. Feeling great!',
            'Weekend vibes only.',
            'Who else loves coding in PHP?',
            'New phone, who dis?',
            'Happy birthday to my best friend!',
            'Movie night with family.',
            'Learning Laravel 12 - amazing framework!',
            'Good morning everyone!',
        ];

        $posts = collect();
        foreach ($postTexts as $i => $text) {
            $posts->push(Post::create([
                'user_id' => $users[$i % $users->count()]->id,
                'content' => $text,
                'type' => 'text',
            ]));
        }

        // Likes & Comments
        foreach ($posts as $post) {
            $likers = $users->random(rand(2, 5));
            foreach ($likers as $liker) {
                Like::firstOrCreate([
                    'user_id' => $liker->id,
                    'likeable_id' => $post->id,
                    'likeable_type' => Post::class,
                ]);
            }

            Comment::create([
                'user_id' => $users->random()->id,
                'post_id' => $post->id,
                'content' => fake()->sentence(),
            ]);
        }

        // Groups
        $techGroup = Group::create([
            'owner_id' => $demo->id,
            'name' => 'Laravel Developers India',
            'slug' => 'laravel-developers-india',
            'description' => 'A community for Laravel developers in India',
            'privacy' => 'public',
        ]);
        $techGroup->members()->attach($users->take(5)->pluck('id')->toArray(), ['role' => 'member', 'status' => 'approved']);

        $travelGroup = Group::create([
            'owner_id' => $users[2]->id,
            'name' => 'Travel Enthusiasts',
            'slug' => 'travel-enthusiasts',
            'description' => 'Share your travel stories and photos',
            'privacy' => 'public',
        ]);
        $travelGroup->members()->attach([$users[2]->id, $demo->id, $users[3]->id], ['role' => 'member', 'status' => 'approved']);

        Post::create([
            'user_id' => $demo->id,
            'group_id' => $techGroup->id,
            'content' => 'Welcome to Laravel Developers India group!',
            'type' => 'text',
        ]);

        // Pages
        $techPage = Page::create([
            'owner_id' => $demo->id,
            'name' => 'Tech News Daily',
            'slug' => 'tech-news-daily',
            'description' => 'Latest technology news and updates',
            'category' => 'Media/News',
        ]);
        $techPage->followers()->attach($users->take(4)->pluck('id')->toArray());

        Page::create([
            'owner_id' => $users[5]->id,
            'name' => 'Foodie Paradise',
            'slug' => 'foodie-paradise',
            'description' => 'Best food recipes and restaurant reviews',
            'category' => 'Restaurant',
        ]);

        Post::create([
            'user_id' => $demo->id,
            'page_id' => $techPage->id,
            'content' => 'Laravel 12 released! Check out the new features.',
            'type' => 'text',
        ]);

        $this->command?->info('Dummy data seeded!');
        $this->command?->info('Login: demo@newbook.test OR 9876543210 | Password: password');
    }
}
