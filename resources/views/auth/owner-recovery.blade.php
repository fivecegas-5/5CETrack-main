<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Owner recovery · Five CE Gas</title>
</head>
<body>
    <main aria-labelledby="owner-recovery-title">
        <h1 id="owner-recovery-title">Owner account recovery</h1>
        <p>Set a new password for the designated global owner account.</p>
        <form method="POST">
            @csrf
            <label for="password">New password</label>
            <input id="password" type="password" name="password" minlength="10" required autocomplete="new-password">

            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" minlength="10" required autocomplete="new-password">

            @error('password')
                <p role="alert">{{ $message }}</p>
            @enderror

            <button type="submit">Set owner password</button>
        </form>
    </main>
</body>
</html>
