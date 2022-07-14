import React, { useState, useEffect } from 'react'
import Chat, { Bubble, useMessages, LocaleProvider } from '@chatui/core'
import '@chatui/core/dist/index.css'
import './App.css'

export default function App() {
  const { messages, appendMsg, setTyping } = useMessages([])
  const [sessionId, setSessionId] = useState()

  const fetchSession = async () => {
    if (sessionId) return

    const response = await fetch('https://engine.memori.ai/memori/v2/session', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        memoriId: '1afe57c6-1b69-4a61-96ea-52bf7b8d158e'
      })
    })

    const { sessionID, currentState, ...resp } = await response.json()

    if (sessionID && currentState && resp.resultCode === 0) {
      setSessionId(sessionID)

      console.log('fetched session', sessionID, currentState)
      if (currentState.emission) {
        appendMsg({
          type: 'text',
          content: { text: currentState.emission }
        })
      }
    }
  }

  useEffect(() => {
    if (!sessionId) fetchSession()
  }, [])

  const handleSend = async (type, val) => {
    if (!sessionId) {
      console.error('No session ID')
      return
    }

    if (type === 'text' && val.trim()) {
      appendMsg({
        type: 'text',
        content: { text: val },
        position: 'right'
      })

      setTyping(true)

      const response = await fetch(`https://engine.memori.ai/memori/v2/TextEnteredEvent/${sessionId}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          text: val.trim()
        })
      })
      const { currentState } = await response.json()

      if (currentState.emission)
        appendMsg({
          type: 'text',
          content: { text: currentState.emission }
        })
    }
  }

  function renderMessageContent(msg) {
    const { content } = msg
    return <Bubble content={content.text} />
  }

  return (
    <LocaleProvider locale="en-US">
      <Chat
        locale="en-US"
        navbar={{ title: 'Parla con Nunzio' }}
        messages={messages}
        renderMessageContent={renderMessageContent}
        onSend={handleSend}
        placeholder="Chiedimi qualcosa..."
      />
    </LocaleProvider>
  )
}
