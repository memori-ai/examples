const writeNewMessage = (text, isUser = false) => {
  let newMessage = document.createElement("p");
  newMessage.textContent = `${isUser ? "Tu" : "Nunzio"}: ${text}`;
  document.querySelector(".chat").appendChild(newMessage);
};

let state = null;

fetch("https://engine.memori.ai/memori/v2/session", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    memoriId: "1afe57c6-1b69-4a61-96ea-52bf7b8d158e",
    birthDate: "1986-04-24T13:38:07.728Z"
  }),
})
  .then((response) => response.json())
  .then((result) => {
    state = result;
    console.log("state", state);

    if (state.currentState.emission)
      writeNewMessage(state.currentState.emission);
    document.getElementById("question").focus();
  })
  .catch((error) => console.log("error", error));

document.getElementById("go").addEventListener("click", (event) => {
  const submittedText = document.getElementById("question").value ?? "";
  writeNewMessage(submittedText, true);

  console.log("session", state, state.sessionID);
  fetch(
    `https://engine.memori.ai/memori/v2/TextEnteredEvent/${state.sessionID}`,
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        text: submittedText,
      }),
    }
  )
    .then((response) => response.json())
    .then((result) => {
      state = result;
      console.log("state", state);

      if (state.currentState.emission)
        writeNewMessage(state.currentState.emission);
      document.getElementById("question").value = "";
      document.getElementById("question").focus();
    })
    .catch((error) => console.log("error", error));
});
