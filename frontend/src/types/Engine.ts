export interface Engine {
  id: string
  name: string
  description: string
  enabled: boolean
  isCurrent?: boolean
  isReady?: boolean
  capabilities?: {
    javascript: boolean
    css: boolean
    html5: boolean
    extensions?: boolean
    sandbox?: boolean
    private_mode?: boolean
    tracking_protection?: boolean
  }
}
