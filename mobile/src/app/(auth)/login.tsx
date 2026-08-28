import { zodResolver } from '@hookform/resolvers/zod';
import * as Linking from 'expo-linking';
import { useEffect, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import {
  AccessibilityInfo,
  ActivityIndicator,
  Image,
  KeyboardAvoidingView,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from 'react-native';
import { z } from 'zod';

import { ApiError } from '@/api/api-error';
import { useAuth } from '@/auth/auth-context';
import { NativeGoogleSignInError } from '@/auth/google-sign-in';
import { getWebBaseUrl } from '@/config/web-environment';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

const loginSchema = z.object({
  email: z.string().trim().min(1, 'Enter your email address.').email('Enter a valid email address.'),
  password: z.string().min(1, 'Enter your password.'),
});

type LoginValues = z.infer<typeof loginSchema>;

const webUrl = getWebBaseUrl();

function getButtonOpacity(disabled: boolean, pressed: boolean): number {
  if (disabled) {
    return 0.55;
  }

  return pressed ? 0.82 : 1;
}

export default function LoginScreen() {
  const {
    cancelGoogleLink,
    confirmGoogleLink,
    isGooglePasswordRequired,
    isGoogleSignInAvailable,
    isLoggingIn,
    isLoggingInWithGoogle,
    login,
    loginWithGoogle,
  } = useAuth();
  const { colors } = useTheme();
  const {
    control,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  });
  const [formMessage, setFormMessage] = useState<string | null>(null);
  const [cooldownSeconds, setCooldownSeconds] = useState(0);
  const [googleCooldownSeconds, setGoogleCooldownSeconds] = useState(0);
  const [googlePassword, setGooglePassword] = useState('');
  const [googlePasswordError, setGooglePasswordError] = useState<string | null>(null);
  const [isStartingGoogleSignIn, setIsStartingGoogleSignIn] = useState(false);

  useEffect(() => {
    if (cooldownSeconds <= 0) {
      return;
    }
    const timer = setTimeout(() => setCooldownSeconds((seconds) => seconds - 1), 1_000);
    return () => clearTimeout(timer);
  }, [cooldownSeconds]);

  useEffect(() => {
    if (googleCooldownSeconds <= 0) {
      return;
    }
    const timer = setTimeout(() => setGoogleCooldownSeconds((seconds) => seconds - 1), 1_000);
    return () => clearTimeout(timer);
  }, [googleCooldownSeconds]);

  async function submit(values: LoginValues) {
    setFormMessage(null);
    try {
      await login(values);
      AccessibilityInfo.announceForAccessibility('Signed in.');
    } catch (error) {
      if (error instanceof ApiError) {
        let mappedFieldMessage: string | undefined;
        Object.entries(error.validationErrors).forEach(([field, messages]) => {
          if ((field === 'email' || field === 'password') && messages[0]) {
            setError(field, { message: messages[0] });
            mappedFieldMessage ??= messages[0];
          }
        });
        if (error.status === 429) {
          setCooldownSeconds(error.retryAfterSeconds ?? 60);
        }
        if (!mappedFieldMessage) {
          setFormMessage(error.message);
        }
        AccessibilityInfo.announceForAccessibility(mappedFieldMessage ?? error.message);
        return;
      }
      setFormMessage('Sign in could not be completed. Try again.');
    }
  }

  function showGoogleError(error: unknown) {
    let message = 'Google sign-in could not be completed. Try again.';

    if (error instanceof ApiError) {
      message = error.message;
      if (error.status === 429) {
        setGoogleCooldownSeconds(error.retryAfterSeconds ?? 60);
      }
    } else if (error instanceof NativeGoogleSignInError) {
      message = error.message;
    }

    setFormMessage(message);
    AccessibilityInfo.announceForAccessibility(message);
  }

  async function submitGoogle() {
    setFormMessage(null);
    setGooglePasswordError(null);
    setIsStartingGoogleSignIn(true);

    try {
      const result = await loginWithGoogle();
      if (result === 'authenticated') {
        AccessibilityInfo.announceForAccessibility('Signed in.');
      } else if (result === 'password-required') {
        AccessibilityInfo.announceForAccessibility(
          'Confirm your Delight password to continue.',
        );
      }
    } catch (error) {
      showGoogleError(error);
    } finally {
      setIsStartingGoogleSignIn(false);
    }
  }

  async function submitGooglePassword() {
    setFormMessage(null);
    setGooglePasswordError(null);

    if (!googlePassword) {
      const message = 'Enter your Delight password.';
      setGooglePasswordError(message);
      AccessibilityInfo.announceForAccessibility(message);
      return;
    }

    try {
      await confirmGoogleLink(googlePassword);
      setGooglePassword('');
      AccessibilityInfo.announceForAccessibility('Signed in.');
    } catch (error) {
      if (error instanceof ApiError && error.validationErrors.password?.[0]) {
        const message = error.validationErrors.password[0];
        setGooglePasswordError(message);
        AccessibilityInfo.announceForAccessibility(message);
        return;
      }

      showGoogleError(error);
    }
  }

  function cancelGooglePassword() {
    cancelGoogleLink();
    setGooglePassword('');
    setGooglePasswordError(null);
    setFormMessage(null);
  }

  const isGoogleBusy = isStartingGoogleSignIn || isLoggingInWithGoogle;
  const isGoogleConfirmationDisabled = isGoogleBusy || googleCooldownSeconds > 0;
  const disabled = isLoggingIn || isGoogleBusy || cooldownSeconds > 0;
  const buttonLabel = cooldownSeconds > 0 ? `Try again in ${cooldownSeconds}s` : 'Sign in';
  const googleButtonLabel = googleCooldownSeconds > 0
    ? `Try Google again in ${googleCooldownSeconds}s`
    : 'Continue with Google';
  const openWebRoute = async (path: string) => {
    try {
      await Linking.openURL(`${webUrl}${path}`);
    } catch {
      const message = 'The web page could not be opened. Try again.';
      setFormMessage(message);
      AccessibilityInfo.announceForAccessibility(message);
    }
  };

  return (
    <KeyboardAvoidingView
      behavior={process.env.EXPO_OS === 'ios' ? 'padding' : 'height'}
      style={{ flex: 1, backgroundColor: colors.background }}
    >
      <ScrollView
        keyboardShouldPersistTaps="handled"
        contentInsetAdjustmentBehavior="automatic"
        contentContainerStyle={{
          flexGrow: 1,
          justifyContent: 'center',
          gap: 32,
          padding: themeTokens.spacing.screen,
        }}
      >
        <View accessibilityRole="header" style={{ alignItems: 'center', gap: 8 }}>
          <Image
            accessibilityLabel="Delight"
            accessibilityRole="image"
            source={require('../../../assets/images/delight-logo.png')}
            style={{ width: 68, height: 68 }}
          />
          <Text
            selectable
            style={{ color: colors.mutedText, fontSize: 13, fontWeight: '700', letterSpacing: 2.2 }}
          >
            DELIGHT
          </Text>
          <Text
            selectable
            style={{ color: colors.text, fontSize: 32, fontWeight: '700', letterSpacing: -0.8, marginTop: 8 }}
          >
            Welcome back
          </Text>
          <Text
            selectable
            style={{ color: colors.mutedText, fontSize: 17, lineHeight: 25, textAlign: 'center' }}
          >
            Continue your reading rhythm.
          </Text>
        </View>

        <View style={{ gap: 18 }}>
          {isGoogleSignInAvailable ? (
            <>
              {isGooglePasswordRequired ? (
                <View
                  accessibilityLiveRegion="polite"
                  style={{
                    gap: 12,
                    padding: 16,
                    borderWidth: 1,
                    borderColor: colors.border,
                    borderRadius: themeTokens.radius.card,
                    backgroundColor: colors.surface,
                  }}
                >
                  <Text selectable style={{ color: colors.text, fontSize: 17, fontWeight: '700' }}>
                    Confirm your Delight password
                  </Text>
                  <Text selectable style={{ color: colors.mutedText, fontSize: 14, lineHeight: 21 }}>
                    This Google email matches an existing Delight account. Enter its password to link them safely.
                  </Text>
                  <TextInput
                    accessibilityLabel="Delight password for Google account"
                    accessibilityHint="Confirms that you own the existing Delight account"
                    autoCapitalize="none"
                    autoComplete="current-password"
                    onChangeText={setGooglePassword}
                    secureTextEntry
                    value={googlePassword}
                    style={{
                      minHeight: 50,
                      paddingHorizontal: 14,
                      color: colors.text,
                      fontSize: 17,
                      borderWidth: 1,
                      borderColor: googlePasswordError ? colors.danger : colors.border,
                      borderRadius: themeTokens.radius.control,
                      backgroundColor: colors.input,
                    }}
                  />
                  {googlePasswordError ? (
                    <Text selectable accessibilityLiveRegion="assertive" style={{ color: colors.danger }}>
                      {googlePasswordError}
                    </Text>
                  ) : null}
                  <View style={{ flexDirection: 'row', gap: 12 }}>
                    <Pressable
                      accessibilityRole="button"
                      accessibilityLabel="Cancel Google account linking"
                      disabled={isGoogleBusy}
                      onPress={cancelGooglePassword}
                      style={({ pressed }) => ({
                        minHeight: themeTokens.minimumTouchTarget,
                        flex: 1,
                        alignItems: 'center',
                        justifyContent: 'center',
                        borderWidth: 1,
                        borderColor: colors.border,
                        borderRadius: themeTokens.radius.control,
                        opacity: getButtonOpacity(isGoogleBusy, pressed),
                      })}
                    >
                      <Text style={{ color: colors.text, fontSize: 16, fontWeight: '600' }}>Cancel</Text>
                    </Pressable>
                    <Pressable
                      accessibilityRole="button"
                      accessibilityLabel="Confirm Google account linking"
                      disabled={isGoogleConfirmationDisabled}
                      onPress={submitGooglePassword}
                      style={({ pressed }) => ({
                        minHeight: themeTokens.minimumTouchTarget,
                        flex: 1,
                        alignItems: 'center',
                        justifyContent: 'center',
                        borderRadius: themeTokens.radius.control,
                        backgroundColor: colors.primary,
                        opacity: getButtonOpacity(isGoogleConfirmationDisabled, pressed),
                      })}
                    >
                      {isLoggingInWithGoogle ? (
                        <ActivityIndicator accessibilityLabel="Confirming account" color={colors.primaryContrast} />
                      ) : (
                        <Text style={{ color: colors.primaryContrast, fontSize: 16, fontWeight: '700' }}>
                          {googleCooldownSeconds > 0
                            ? `Try again in ${googleCooldownSeconds}s`
                            : 'Confirm'}
                        </Text>
                      )}
                    </Pressable>
                  </View>
                </View>
              ) : (
                <Pressable
                  accessibilityRole="button"
                  accessibilityLabel="Continue with Google"
                  accessibilityHint="Signs in to Delight with a Google account"
                  disabled={isGoogleBusy || isLoggingIn || googleCooldownSeconds > 0}
                  onPress={submitGoogle}
                  style={({ pressed }) => ({
                    minHeight: 50,
                    alignItems: 'center',
                    justifyContent: 'center',
                    borderWidth: 1,
                    borderColor: colors.border,
                    borderRadius: themeTokens.radius.control,
                    backgroundColor: colors.surface,
                    opacity: getButtonOpacity(
                      isGoogleBusy || isLoggingIn || googleCooldownSeconds > 0,
                      pressed,
                    ),
                  })}
                >
                  {isGoogleBusy ? (
                    <ActivityIndicator accessibilityLabel="Signing in with Google" color={colors.text} />
                  ) : (
                    <Text style={{ color: colors.text, fontSize: 17, fontWeight: '700' }}>
                      {googleButtonLabel}
                    </Text>
                  )}
                </Pressable>
              )}
              <View accessibilityElementsHidden style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                <View style={{ height: 1, flex: 1, backgroundColor: colors.border }} />
                <Text style={{ color: colors.mutedText, fontSize: 14 }}>or</Text>
                <View style={{ height: 1, flex: 1, backgroundColor: colors.border }} />
              </View>
            </>
          ) : null}
          <Controller
            control={control}
            name="email"
            render={({ field: { onBlur, onChange, value } }) => (
              <View style={{ gap: 7 }}>
                <Text style={{ color: colors.text, fontSize: 15, fontWeight: '600' }}>
                  Email
                </Text>
                <TextInput
                  accessibilityLabel="Email"
                  accessibilityHint="Enter the email address for your Delight account"
                  autoCapitalize="none"
                  autoComplete="email"
                  keyboardType="email-address"
                  onBlur={onBlur}
                  onChangeText={onChange}
                  value={value}
                  style={{
                    minHeight: 50,
                    paddingHorizontal: 14,
                    color: colors.text,
                    fontSize: 17,
                    borderWidth: 1,
                    borderColor: errors.email ? colors.danger : colors.border,
                    borderRadius: themeTokens.radius.control,
                    backgroundColor: colors.input,
                  }}
                />
                {errors.email?.message ? (
                  <Text
                    selectable
                    accessibilityLiveRegion="polite"
                    style={{ color: colors.danger }}
                  >
                    {errors.email.message}
                  </Text>
                ) : null}
              </View>
            )}
          />
          <Controller
            control={control}
            name="password"
            render={({ field: { onBlur, onChange, value } }) => (
              <View style={{ gap: 7 }}>
                <Text style={{ color: colors.text, fontSize: 15, fontWeight: '600' }}>
                  Password
                </Text>
                <TextInput
                  accessibilityLabel="Password"
                  accessibilityHint="Enter your Delight password"
                  autoCapitalize="none"
                  autoComplete="current-password"
                  onBlur={onBlur}
                  onChangeText={onChange}
                  secureTextEntry
                  value={value}
                  style={{
                    minHeight: 50,
                    paddingHorizontal: 14,
                    color: colors.text,
                    fontSize: 17,
                    borderWidth: 1,
                    borderColor: errors.password ? colors.danger : colors.border,
                    borderRadius: themeTokens.radius.control,
                    backgroundColor: colors.input,
                  }}
                />
                {errors.password?.message ? (
                  <Text
                    selectable
                    accessibilityLiveRegion="polite"
                    style={{ color: colors.danger }}
                  >
                    {errors.password.message}
                  </Text>
                ) : null}
              </View>
            )}
          />
          {formMessage ? (
            <Text
              selectable
              accessibilityLiveRegion="assertive"
              style={{ color: colors.danger, fontSize: 15, lineHeight: 22 }}
            >
              {formMessage}
            </Text>
          ) : null}
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Sign in"
            accessibilityHint="Signs in to Delight on this device"
            disabled={disabled}
            onPress={handleSubmit(submit)}
            style={({ pressed }) => ({
              minHeight: 50,
              alignItems: 'center',
              justifyContent: 'center',
              borderRadius: themeTokens.radius.control,
              backgroundColor: colors.primary,
              opacity: getButtonOpacity(disabled, pressed),
            })}
          >
            {isLoggingIn ? (
              <ActivityIndicator
                accessibilityLabel="Signing in"
                color={colors.primaryContrast}
              />
            ) : (
              <Text style={{ color: colors.primaryContrast, fontSize: 17, fontWeight: '700' }}>
                {buttonLabel}
              </Text>
            )}
          </Pressable>
        </View>

        <View style={{ gap: 4 }}>
          <Pressable
            accessibilityRole="link"
            accessibilityLabel="Create an account on the web"
            accessibilityHint="Opens registration in your browser"
            onPress={() => openWebRoute('/register')}
            style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center' }}
          >
            <Text
              style={{ color: colors.primary, textAlign: 'center', fontSize: 16, fontWeight: '600' }}
            >
              Create an account
            </Text>
          </Pressable>
          <Pressable
            accessibilityRole="link"
            accessibilityLabel="Reset your password on the web"
            accessibilityHint="Opens password reset in your browser"
            onPress={() => openWebRoute('/forgot-password')}
            style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center' }}
          >
            <Text
              style={{ color: colors.primary, textAlign: 'center', fontSize: 16, fontWeight: '600' }}
            >
              Reset your password
            </Text>
          </Pressable>
          {!isGoogleSignInAvailable ? (
            <Text
              selectable
              style={{
                color: colors.mutedText,
                textAlign: 'center',
                fontSize: 14,
                lineHeight: 21,
                marginTop: 8,
              }}
            >
              Created your account with Google? Set a password on the web.
            </Text>
          ) : null}
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
