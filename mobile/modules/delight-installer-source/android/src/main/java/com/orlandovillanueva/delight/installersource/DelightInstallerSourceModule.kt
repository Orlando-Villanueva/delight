package com.orlandovillanueva.delight.installersource

import android.os.Build
import expo.modules.kotlin.modules.Module
import expo.modules.kotlin.modules.ModuleDefinition

class DelightInstallerSourceModule : Module() {
  override fun definition() = ModuleDefinition {
    Name("DelightInstallerSource")

    Function("getInstallerPackageName") {
      val context = appContext.reactContext ?: return@Function null
      val packageName = context.packageName

      if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
        return@Function context.packageManager.getInstallSourceInfo(packageName).installingPackageName
      }

      @Suppress("DEPRECATION")
      return@Function context.packageManager.getInstallerPackageName(packageName)
    }
  }
}
