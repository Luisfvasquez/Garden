import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { accountApi, type ProfileInput, type SettingsInput } from '@/api/account'
import { blocksApi } from '@/api/blocks'
import { devicesApi } from '@/api/devices'
import { useAuthStore } from '@/stores/auth'

export function useSettings() {
  return useQuery({
    queryKey: ['me', 'settings'],
    queryFn: () => accountApi.getSettings(),
  })
}

export function useUpdateSettings() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: SettingsInput) => accountApi.updateSettings(input),
    onSuccess: (settings) => qc.setQueryData(['me', 'settings'], settings),
  })
}

export function useUpdateProfile() {
  const auth = useAuthStore()
  return useMutation({
    mutationFn: (input: ProfileInput) => accountApi.updateProfile(input),
    onSuccess: (me) => auth.setUser(me),
  })
}

export function useUploadAvatar() {
  const auth = useAuthStore()
  return useMutation({
    mutationFn: (file: File) => accountApi.uploadAvatar(file),
    onSuccess: (me) => auth.setUser(me),
  })
}

export function useRotateHandle() {
  const auth = useAuthStore()
  return useMutation({
    mutationFn: () => accountApi.rotateHandle(),
    onSuccess: (me) => auth.setUser(me),
  })
}

export function useDevices() {
  return useQuery({ queryKey: ['auth', 'devices'], queryFn: () => devicesApi.list() })
}

export function useRevokeDevice() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => devicesApi.revoke(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['auth', 'devices'] }),
  })
}

export function useBlocks() {
  return useQuery({ queryKey: ['blocks'], queryFn: () => blocksApi.list() })
}

export function useBlockUser() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: { postal_handle: string; reason?: string | null }) =>
      blocksApi.block(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['blocks'] }),
  })
}

export function useUnblockUser() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (userId: string) => blocksApi.unblock(userId),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['blocks'] }),
  })
}
