/*
 * Generates the PWA icons as flat PNGs (no image deps): a violet ground with a
 * cream wax-seal disc. Run: node scripts/make-icons.mjs
 */
import { deflateSync } from 'node:zlib'
import { writeFileSync, mkdirSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const outDir = resolve(dirname(fileURLToPath(import.meta.url)), '../public/icons')
mkdirSync(outDir, { recursive: true })

const VIOLET = [93, 78, 140]
const CREAM = [247, 241, 227]

function png(size, sealRatio) {
  const cx = size / 2
  const cy = size / 2
  const r = size * sealRatio

  // Raw RGBA scanlines, each prefixed with filter byte 0.
  const raw = Buffer.alloc((size * 4 + 1) * size)
  for (let y = 0; y < size; y++) {
    const row = y * (size * 4 + 1)
    raw[row] = 0
    for (let x = 0; x < size; x++) {
      const inSeal = (x - cx) ** 2 + (y - cy) ** 2 <= r * r
      const [rr, gg, bb] = inSeal ? CREAM : VIOLET
      const p = row + 1 + x * 4
      raw[p] = rr
      raw[p + 1] = gg
      raw[p + 2] = bb
      raw[p + 3] = 255
    }
  }

  const chunk = (type, data) => {
    const len = Buffer.alloc(4)
    len.writeUInt32BE(data.length)
    const body = Buffer.concat([Buffer.from(type, 'ascii'), data])
    const crc = Buffer.alloc(4)
    crc.writeUInt32BE(crc32(body) >>> 0)
    return Buffer.concat([len, body, crc])
  }

  const ihdr = Buffer.alloc(13)
  ihdr.writeUInt32BE(size, 0)
  ihdr.writeUInt32BE(size, 4)
  ihdr[8] = 8 // bit depth
  ihdr[9] = 6 // colour type RGBA

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('IDAT', deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ])
}

const CRC_TABLE = (() => {
  const t = new Uint32Array(256)
  for (let n = 0; n < 256; n++) {
    let c = n
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
    t[n] = c >>> 0
  }
  return t
})()

function crc32(buf) {
  let c = 0xffffffff
  for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8)
  return c ^ 0xffffffff
}

writeFileSync(resolve(outDir, '192.png'), png(192, 0.28))
writeFileSync(resolve(outDir, '512.png'), png(512, 0.28))
writeFileSync(resolve(outDir, 'maskable.png'), png(512, 0.22))
console.log('icons written to', outDir)
