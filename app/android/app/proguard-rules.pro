# Add project specific ProGuard rules here.
# You can control the set of applied configuration files using the
# proguardFiles setting in build.gradle.
#
# For more details, see
#   http://developer.android.com/guide/developing/tools/proguard.html

# Preserva informação de linha para stack traces legíveis no Sentry
-keepattributes SourceFile,LineNumberTable
-renamesourcefileattribute SourceFile

# -------------------------------------------------------------------------
# Capacitor — obrigatório para todas as builds com minifyEnabled true
# -------------------------------------------------------------------------
-keep class com.getcapacitor.** { *; }
-keep @com.getcapacitor.annotation.CapacitorPlugin class * { *; }
-keepnames class com.getcapacitor.** { *; }

# Capacitor Plugins instalados
-keep class com.capacitorjs.plugins.** { *; }
-keep class io.ionic.libs.** { *; }

# Push Notifications (Firebase + Capacitor)
-keep class com.google.firebase.** { *; }
-keep class com.google.android.gms.** { *; }
-dontwarn com.google.firebase.**
-dontwarn com.google.android.gms.**

# Camera Plugin
-keep class com.capacitorjs.plugins.camera.** { *; }

# Filesystem Plugin
-keep class com.capacitorjs.plugins.filesystem.** { *; }

# Preferences Plugin
-keep class com.capacitorjs.plugins.preferences.** { *; }

# Status Bar
-keep class com.capacitorjs.plugins.statusbar.** { *; }

# Splash Screen
-keep class com.capacitorjs.plugins.splashscreen.** { *; }

# Keyboard
-keep class com.capacitorjs.plugins.keyboard.** { *; }

# Haptics
-keep class com.capacitorjs.plugins.haptics.** { *; }

# App Plugin
-keep class com.capacitorjs.plugins.app.** { *; }

# Network Plugin
-keep class com.capacitorjs.plugins.network.** { *; }

# Kotlin coroutines (requerido por vários plugins)
-keepnames class kotlinx.coroutines.internal.MainDispatcherFactory {}
-keepnames class kotlinx.coroutines.CoroutineExceptionHandler {}
-dontwarn kotlinx.coroutines.**

# WebView JavaScript interface
-keepclassmembers class fqcn.of.javascript.interface.for.webview {
   public *;
}

# Sentry — stack traces e upload de sourcemaps
-keep class io.sentry.** { *; }
-dontwarn io.sentry.**
