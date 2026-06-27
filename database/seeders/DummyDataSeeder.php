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
                'role' => $data['email'] === 'demo@newbook.test' ? 'admin' : 'user',
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

        // Advertisements Seeding
        $ad1 = \App\Models\Advertisement::create([
            'user_id' => $demo->id,
            'title' => 'Web Design Bootcamp',
            'description' => 'Learn HTML, CSS, JavaScript, Tailwind, and Laravel. Sign up today to get 50% off on our full bootcamp course!',
            'image_path' => null,
            'cta_text' => 'Sign Up',
            'plan' => 'monthly',
            'amount' => 999.00,
            'payment_status' => 'paid',
            'status' => 'approved',
            'expires_at' => now()->addDays(20),
        ]);

        $ad2 = \App\Models\Advertisement::create([
            'user_id' => $users[1]->id, // Yogesh Kumar
            'title' => 'Hire Dedicated Laravel Experts',
            'description' => 'Looking for top-notch Laravel developers? Hire our dedicated team to build high-performance web applications.',
            'image_path' => null,
            'cta_text' => 'Contact Us',
            'plan' => 'quarterly',
            'amount' => 2499.00,
            'payment_status' => 'paid',
            'status' => 'pending_approval',
        ]);

        $ad3 = \App\Models\Advertisement::create([
            'user_id' => $users[2]->id, // Priya Sharma
            'title' => 'Nature Photography Workshops',
            'description' => 'Join our quarterly nature photography sessions. Limited seats available! Sign up to reserve your spot.',
            'image_path' => null,
            'cta_text' => 'Learn More',
            'plan' => 'yearly',
            'amount' => 7999.00,
            'payment_status' => 'pending',
            'status' => 'pending_payment',
        ]);

        // Leads Seeding
        \App\Models\Lead::create([
            'advertisement_id' => $ad1->id,
            'user_id' => $users[2]->id, // Priya
            'name' => $users[2]->name,
            'email' => $users[2]->email,
            'phone' => $users[2]->phone,
            'notes' => 'I want to join the JavaScript module and learn React.',
        ]);

        \App\Models\Lead::create([
            'advertisement_id' => $ad1->id,
            'user_id' => $users[3]->id, // Rahul
            'name' => $users[3]->name,
            'email' => $users[3]->email,
            'phone' => $users[3]->phone,
            'notes' => 'Please send me the syllabus details and fee structure.',
        ]);

        \App\Models\Lead::create([
            'advertisement_id' => $ad1->id,
            'user_id' => $users[7]->id, // Amit
            'name' => $users[7]->name,
            'email' => $users[7]->email,
            'phone' => $users[7]->phone,
            'notes' => 'Do you offer weekend batches for working professionals?',
        ]);

        $this->command?->info('Dummy data, advertisements, and leads seeded!');
        $this->command?->info('Admin Ad Panel URL: /admin/ads');
        $this->command?->info('Login: demo@newbook.test (admin) | yogesh@newbook.test (customer) | Password: password');
        $this->command?->info('Admin panel: /admin/ads | Customer ads: /ads');
    }
}
