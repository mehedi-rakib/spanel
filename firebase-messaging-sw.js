importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-auth.js');

firebase.initializeApp({
    apiKey: "AIzaSyBV04_K-ISGEbYcj_lRR7uEp7IaCEeye38",
    authDomain: "daily-needs-bd.firebaseapp.com",
    projectId: "daily-needs-bd",
    storageBucket: "daily-needs-bd.firebasestorage.app",
    messagingSenderId: "615872331134",
    appId: "1:615872331134:web:034c5d01f7a63629c6472f",
    measurementId: "G-GC9Z038R8Z"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function(payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body || '',
        icon: payload.data.icon || ''
    });
});