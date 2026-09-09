import { StarterKit } from '@tiptap/starter-kit'
import type { Extensions, JSONContent } from '@tiptap/core'

/**
 * The restricted letter editor: bold, italic, underline, blockquote, horizontal
 * rule. Nothing else (docs/sistema-diseno.md). The backend enforces the same
 * allow-list — this just keeps the editor honest.
 */
export const letterExtensions: Extensions = [
  StarterKit.configure({
    heading: false,
    bulletList: false,
    orderedList: false,
    listItem: false,
    listKeymap: false,
    code: false,
    codeBlock: false,
    strike: false,
    link: false,
  }),
]

export const EMPTY_DOC: JSONContent = { type: 'doc', content: [] }
