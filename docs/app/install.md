# Install the app

This page tells you how to get the Kinlore app, which phones it runs on, and which permissions it asks for.

## Get the app

The Kinlore app is not in the App Store or in Google Play.
You build the app yourself from its source code.
The source code is in the `kinlore-mobile` repository of the Kinlore organization on GitHub.
The README file of that repository has the build steps.

There are three ways to build the app:

- **Development build.** You build the app with Expo and run it on a simulator, an emulator or a phone that is connected to your computer. The Expo Go app does not work with Kinlore.
- **Release build on your own phone.** You build a release version and install it on a connected phone. On iOS, you sign the app with your own Apple developer team. On Android, you sign the app with your own key before you share the APK file.
- **EAS Build.** EAS Build is the build service of Expo. The repository has build profiles for it. You need your own Expo account and the `eas` command-line tool.

You need a computer for each of these ways: Node.js, and Xcode for iOS or Android Studio for Android.
If you are not the person who builds the app, ask the owner of your family's server how to get it.

## Supported phones

- The app is for iOS and Android.
- On iOS, the app can also run on an iPad.
- The app shows its screens in portrait orientation only.
- The app is tested on the iOS simulator and on the Android emulator. It is not tested on real phones yet.

Use the newest release of the app with the newest release of the server. The server and the app change together.
The app version shows at the bottom of the **Settings** tab, for example "Kinlore 1.2.0".

## Permissions

The app asks for one permission: access to the photos and videos on the phone.

- The app asks the first time that you open the photo picker to add photos or videos.
- On iOS, the request says "Allow Kinlore to access your photos to upload memories."
- The app reads only the items that you pick. It also reads the date and the place that are stored in each item, so the moment gets the right date.
- On Android, the app asks for photos and videos only. It does not ask for music or audio.

The app does not ask for the camera, the microphone or the location service.

The app build also declares the right to save photos to the phone. The app does not use this right.

### If you refused the permission

If you refuse access, the **Photos** tab of the add screen shows "Allow access to your photos". You can still add a milestone, a story or a growth record.
To give access later, tap **Open Settings** on that tab. Or open the Settings app of your phone, find Kinlore, and allow access to photos and videos. When you come back to the app, the albums show.

## Network

- The app connects to your family's server over HTTPS.
- The app tries plain HTTP only for addresses on a home network, for example `192.168.1.20` or a name that ends in `.local`.

## Next step

Connect the app to your family's server. See [Connect to a server](connect.md).
