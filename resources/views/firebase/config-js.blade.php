self.firebaseConfig = {
    apiKey: @json(config('services.firebase.api_key')),
    authDomain: @json(config('services.firebase.auth_domain')),
    projectId: @json(config('services.firebase.project_id')),
    storageBucket: @json(config('services.firebase.storage_bucket')),
    messagingSenderId: @json(config('services.firebase.messaging_sender_id')),
    appId: @json(config('services.firebase.app_id')),
    measurementId: @json(config('services.firebase.measurement_id')),
};
