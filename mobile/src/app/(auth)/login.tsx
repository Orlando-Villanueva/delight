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
  const { login, isLoggingIn } = useAuth();
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

  useEffect(() => {
    if (cooldownSeconds <= 0) {
      return;
    }
    const timer = setTimeout(() => setCooldownSeconds((seconds) => seconds - 1), 1_000);
    return () => clearTimeout(timer);
  }, [cooldownSeconds]);

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

  const disabled = isLoggingIn || cooldownSeconds > 0;
  const buttonLabel = cooldownSeconds > 0 ? `Try again in ${cooldownSeconds}s` : 'Sign in';
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

        <View
          style={{
            gap: 18,
          }}
        >
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
          <Text
            selectable
            style={{ color: colors.mutedText, textAlign: 'center', fontSize: 14, lineHeight: 21, marginTop: 8 }}
          >
            Created your account with Google? Set a password on the web.
          </Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
