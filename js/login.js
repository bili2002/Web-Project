document.querySelector("form").addEventListener("submit", function (event) {
    const usernameOrFaculty = document.querySelector("input[name='username_or_faculty']").value;
    const password = document.querySelector("input[name='password']").value;

    if (!usernameOrFaculty || !password) {
        alert("Please fill in all fields!");
        event.preventDefault(); 
    }
});